#!/usr/bin/env python3
"""End-to-end test of orchestra-members against a running WordPress. Started by run.sh.

Environment: E2E_BASE_URL, E2E_WP_CLI (wrapper script), E2E_WP_PATH.
"""
import base64
import html
import json
import os
import re
import subprocess
import sys
import tempfile

BASE = os.environ["E2E_BASE_URL"]
WPC = os.environ["E2E_WP_CLI"]
WORK = tempfile.mkdtemp(prefix="orchestra-e2e-")
INACTIVE_MESSAGE = "Your account has been marked as inactive"
failures = []


def check(condition, label):
    print(("PASS " if condition else "FAIL ") + label)
    if not condition:
        failures.append(label)


def wp(*args):
    return subprocess.run([WPC, *args], capture_output=True, text=True).stdout.strip()


def wp_eval(code):
    return wp("eval", code)


def user_id(login):
    return int(wp("user", "get", login, "--field=ID"))


def meta(uid, key):
    return wp_eval(f'echo (string) get_user_meta({uid}, "{key}", true);')


class Session:
    def __init__(self, user=None, password="pass"):
        self.jar = os.path.join(WORK, f"cookies-{user or 'anon'}-{os.urandom(4).hex()}.txt")
        open(self.jar, "w").close()
        self.login_page = ""
        if user:
            _, _, body = self.request("/wp-login.php", data={
                "log": user, "pwd": password, "wp-submit": "Log In", "testcookie": "1",
            }, cookie="wordpress_test_cookie=WP%20Cookie%20check")
            self.login_page = body.decode()

    def request(self, path, data=None, cookie=None, follow=True, headers=None):
        """Returns (status, headers, body)."""
        header_file = os.path.join(WORK, "headers.txt")
        cmd = ["curl", "-s", "-c", self.jar, "-b", self.jar, "-D", header_file, "-o", "-"]
        if follow:
            cmd.append("-L")
        if cookie:
            cmd += ["--cookie", cookie]
        for header in headers or []:
            cmd += ["-H", header]
        if data is not None:
            for key, value in data.items():
                cmd += ["--data-urlencode", f"{key}={value}"]
        cmd.append(BASE + path)
        result = subprocess.run(cmd, capture_output=True)
        raw = open(header_file, encoding="latin-1", newline="").read()
        blocks = [b for b in raw.split("\r\n\r\n") if b.strip()]
        last = blocks[-1] if blocks else ""
        status = int(re.search(r"HTTP/[\d.]+ (\d+)", last).group(1)) if last else 0
        response_headers = {}
        for line in last.split("\r\n")[1:]:
            if ":" in line:
                k, v = line.split(":", 1)
                response_headers[k.strip().lower()] = v.strip()
        return status, response_headers, result.stdout

    def page(self, path):
        status, _, body = self.request(path)
        return status, body.decode()

    def logged_in(self):
        status, page = self.page("/wp-admin/profile.php")
        return status == 200 and 'id="your-profile"' in page


def field(page, name):
    match = re.search(r'name=["\']' + re.escape(name) + r'["\'][^>]*value=["\']([^"\']*)', page) \
        or re.search(r'value=["\']([^"\']*)["\'][^>]*name=["\']' + re.escape(name) + r'["\']', page)
    return html.unescape(match.group(1)) if match else None


def profile_form(session):
    _, page = session.page("/wp-admin/profile.php")
    form = {key: field(page, key) for key in [
        "_wpnonce", "user_id", "email", "nickname", "orchestra_members_profile_nonce",
    ]}
    form.update({"action": "update", "from": "profile", "checkuser_id": form["user_id"]})
    return page, form


def save_profile(session, financial_support, dietary):
    _, form = profile_form(session)
    form["orchestra_members_dietary_restrictions"] = dietary
    if financial_support:
        form["orchestra_members_financial_support"] = "1"
    _, page = session.page_post("/wp-admin/profile.php", form)
    return page


def page_post(self, path, data):
    status, _, body = self.request(path, data=data)
    return status, body.decode()


Session.page_post = page_post

MEMBERS = "/wp-admin/admin.php?page=orchestra-members"


def member_url(uid):
    return f"{MEMBERS}&member={uid}"


def set_status(session, uid, status, nonce=None):
    if nonce is None:
        _, page = session.page(member_url(uid))
        nonce = field(page, "_wpnonce")
    return session.page_post("/wp-admin/admin-post.php", {
        "action": "orchestra_members_set_status", "member": str(uid), "status": status,
        "_wpnonce": nonce or "", "_wp_http_referer": member_url(uid),
    })


def rest_me(login, password):
    token = base64.b64encode(f"{login}:{password}".encode()).decode()
    status, _, _ = Session().request("/?rest_route=/wp/v2/users/me", headers=[f"Authorization: Basic {token}"])
    return status


admin_id, organizer_id, conductor_id = user_id("admin"), user_id("organizer"), user_id("conductor")
member_id, member2_id, sub_id = user_id("member"), user_id("member2"), user_id("sub")

# --- Capabilities reach the roles -----------------------------------------------------------
caps = json.loads(wp_eval(
    'echo json_encode(array_map(fn($r) => array_keys(array_filter(get_role($r)->capabilities)), '
    '["orchestra_member", "orchestra_conductor", "orchestra_organizer"]));'
))
check("orchestra_members_profile_read_own" in caps[0] and "orchestra_members_status_edit" not in caps[0],
      "member role has the own-profile capabilities only")
check("orchestra_members_profile_read_others" not in caps[1], "conductors cannot read other profiles")
check({"orchestra_members_profile_read_others", "orchestra_members_status_edit"} <= set(caps[2]),
      "organizers can read profiles and change the status")
actions = json.loads(wp_eval('echo json_encode(get_option("e2e_actions", []));'))
check(["orchestra_members_member_activated", member_id] in actions, "creating an account fires the activated action")

# --- Members edit their own profile ---------------------------------------------------------
member = Session("member")
check(member.logged_in(), "active member can log in")
page, _ = profile_form(member)
check("Dietary restrictions" in page and 'name="orchestra_members_financial_support"' in page,
      "profile screen shows the orchestra fields")
save_profile(member, True, "Vegetarian\nno nuts")
check(meta(member_id, "orchestra_members_financial_support") == "1", "member saves financial support")
check(meta(member_id, "orchestra_members_dietary_restrictions") == "Vegetarian\nno nuts", "member saves dietary restrictions")
page = save_profile(member, False, "x" * 1001)
check("must not be longer than 1000 characters" in page, "too long dietary restrictions are rejected with a message")
check(meta(member_id, "orchestra_members_dietary_restrictions") == "Vegetarian\nno nuts", "rejected input is not saved")
_, form = profile_form(member)
form["orchestra_members_dietary_restrictions"] = "forged"
form["orchestra_members_profile_nonce"] = "invalid"
member.page_post("/wp-admin/profile.php", form)
check(meta(member_id, "orchestra_members_dietary_restrictions") == "Vegetarian\nno nuts",
      "profile data is not saved without a valid nonce")

save_profile(Session("member2"), False, "Gluten-free")
check(meta(member2_id, "orchestra_members_dietary_restrictions") == "Gluten-free", "second member saves profile")

sub = Session("sub")
_, page = sub.page("/wp-admin/profile.php")
check("Dietary restrictions" not in page, "users without an orchestra role have no orchestra profile section")

# --- Member list and profile visibility -----------------------------------------------------
status, page = member.page(MEMBERS)
check(status == 200 and all(n in page for n in ["Olga", "Carl", "Max", "Mia"]), "members see the member list")
check("member2@example.com" not in page and "Gluten-free" not in page, "members only see names in the list")
check("Sam" not in page and ">admin<" not in page, "list contains only orchestra users")
status, page = member.page(member_url(member_id))
check(status == 200 and "Vegetarian" in page, "members can view their own profile")
status, page = member.page(member_url(member2_id))
check(status == 403 and "Gluten-free" not in page, "members cannot view other profiles")
conductor = Session("conductor")
status, page = conductor.page(member_url(member2_id))
check(status == 403, "conductors cannot view other profiles")
status, _ = sub.page(MEMBERS)
check(status == 403, "users without an orchestra role cannot open the member list")

organizer = Session("organizer")
status, page = organizer.page(MEMBERS)
check(status == 200 and "member2@example.com" in page and "Active" in page, "organizers see emails and status")
status, page = organizer.page(member_url(member2_id))
check(status == 200 and "Gluten-free" in page, "organizers can view other profiles")
check("Set inactive" in page, "organizers see the status form")
_, page = organizer.page(member_url(admin_id))
check("Set inactive" not in page, "administrators have no status form")
_, page = organizer.page(member_url(organizer_id))
check("Set inactive" not in page, "organizers cannot change their own status")

_, page = Session("admin", "admin").page("/wp-admin/users.php")
check("Orchestra status" in page, "user list shows the orchestra status column")

# --- Status changes are protected -----------------------------------------------------------
_, page = organizer.page(member_url(member_id))
nonce = field(page, "_wpnonce")
status, _ = set_status(member, member2_id, "inactive", nonce)
check(status == 403 and meta(member2_id, "orchestra_members_inactive") == "", "members cannot change a status")
set_status(organizer, member_id, "inactive", "invalid")
check(meta(member_id, "orchestra_members_inactive") == "", "status change needs a valid nonce")
_, page = organizer.page(member_url(member_id))
_, page = set_status(organizer, admin_id, "inactive", field(page, "_wpnonce"))
check(meta(admin_id, "orchestra_members_inactive") == "", "administrators cannot be set inactive")

# --- Deactivation ---------------------------------------------------------------------------
member_app_password = wp("user", "application-password", "create", "member", "e2e", "--porcelain")
check(rest_me("member", member_app_password) == 200, "active member can use an application password")

_, page = set_status(organizer, member_id, "inactive")
check("The member is inactive" in page, "organizer sets a member inactive")
check(meta(member_id, "orchestra_members_inactive") == "1", "member is stored as inactive")
check(meta(member_id, "orchestra_members_financial_support") == ""
      and meta(member_id, "orchestra_members_dietary_restrictions") == "",
      "sensitive data is deleted on deactivation")
check(not member.logged_in(), "the inactive member's session has ended")
check(rest_me("member", member_app_password) == 401, "inactive member cannot use an application password")
actions = json.loads(wp_eval('echo json_encode(get_option("e2e_actions", []));'))
check(["orchestra_members_member_deactivated", member_id] in actions, "deactivation fires the deactivated action")

attempt = Session("member")
check(INACTIVE_MESSAGE in attempt.login_page and not attempt.logged_in(),
      "inactive member with the correct password is told the account is inactive")
attempt = Session("member", "wrong")
check(INACTIVE_MESSAGE not in attempt.login_page and "incorrect" in attempt.login_page,
      "a wrong password does not reveal the inactive status")

status, page = organizer.page(MEMBERS)
check("Mia" in page and "Inactive" in page, "organizers see inactive members")
_, page = Session("member2").page(MEMBERS)
check("Mia" not in page, "members do not see inactive members")
_, page = Session("admin", "admin").page(f"/wp-admin/user-edit.php?user_id={member_id}")
check("is deleted. It can be entered again" in page and "disabled" in page,
      "the profile of an inactive member cannot be filled in")

# --- Reactivation ---------------------------------------------------------------------------
_, page = set_status(organizer, member_id, "active")
check("active again" in page and meta(member_id, "orchestra_members_inactive") == "", "organizer reactivates a member")
check(Session("member").logged_in(), "reactivated member can log in")
check(meta(member_id, "orchestra_members_dietary_restrictions") == "", "deleted data stays deleted after reactivation")
actions = json.loads(wp_eval('echo json_encode(get_option("e2e_actions", []));'))
check(actions.count(["orchestra_members_member_activated", member_id]) == 2, "reactivation fires the activated action")

# --- Privacy tools --------------------------------------------------------------------------
export = json.loads(wp_eval(
    '$e = apply_filters("wp_privacy_personal_data_exporters", [])["orchestra-members"];'
    'echo json_encode(call_user_func($e["callback"], "member2@example.com", 1));'
))
values = {item["name"]: item["value"] for item in export["data"][0]["data"]}
check(values.get("Dietary restrictions") == "Gluten-free", "personal data export contains the profile")
erased = json.loads(wp_eval(
    '$e = apply_filters("wp_privacy_personal_data_erasers", [])["orchestra-members"];'
    'echo json_encode(call_user_func($e["callback"], "member2@example.com", 1));'
))
check(erased["items_removed"] and meta(member2_id, "orchestra_members_dietary_restrictions") == "",
      "personal data eraser deletes the profile data")
_, page = Session("admin", "admin").page("/wp-admin/options-privacy.php?tab=policyguide")
check("Orchestra Members" in page and "Inactive members cannot log in." in page, "suggests a privacy policy text")

# --- Uninstall ------------------------------------------------------------------------------
set_status(organizer, member2_id, "inactive")
wp("plugin", "deactivate", "orchestra-members")
check(Session("member2").logged_in(), "deactivating the plugin no longer blocks inactive members")
check(meta(member2_id, "orchestra_members_inactive") == "1", "deactivating the plugin keeps the data")
wp("plugin", "uninstall", "orchestra-members")
remaining = wp_eval('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE \'orchestra_members_%\'");')
check(remaining == "0", "uninstall removes all profile data")

print()
if failures:
    print(f"{len(failures)} check(s) failed")
    sys.exit(1)
print("All checks passed")
