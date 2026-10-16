#!/usr/bin/env python3
"""End-to-end test of orchestra-members against a running WordPress. Started by run.sh.

Members, conductors and organizers use the Orchestra pages (shortcodes); administrators also
wp-admin. Environment: E2E_BASE_URL, E2E_WP_CLI (wrapper script), E2E_WP_PATH.
"""
import base64
import html
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.parse
from html.parser import HTMLParser

BASE = os.environ["E2E_BASE_URL"]
WPC = os.environ["E2E_WP_CLI"]
WORK = tempfile.mkdtemp(prefix="orchestra-e2e-")
INACTIVE_MESSAGE = "Your account has been marked as inactive"
ADMIN_POST = "/wp-admin/admin-post.php"
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


def text(page):
    """The visible text of a page."""
    page = re.sub(r"<(script|style|template)[^>]*>.*?</\1>", " ", page, flags=re.S)
    return html.unescape(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", page)))


class Forms(HTMLParser):
    """All forms of a page with the values a browser would send (without clicking a button)."""

    def __init__(self, page):
        super().__init__(convert_charrefs=True)
        self.forms, self._form, self._select, self._textarea = [], None, None, None
        self.feed(page)

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "form":
            self._form = {"action": a.get("action", ""), "attrs": a, "fields": [], "buttons": []}
            self.forms.append(self._form)
        elif self._form is None:
            return
        elif tag == "input" and a.get("name"):
            kind = a.get("type", "text")
            if kind in ("checkbox", "radio") and "checked" not in a:
                return
            if kind in ("submit", "button", "file"):
                return
            self._form["fields"].append([a["name"], a.get("value", "on" if kind == "checkbox" else "")])
        elif tag == "button" and a.get("name"):
            self._form["buttons"].append([a["name"], a.get("value", "")])
        elif tag == "select" and a.get("name"):
            self._select = [a["name"], None, "multiple" in a]
        elif tag == "option" and self._select is not None:
            if "selected" in a or (self._select[1] is None and not self._select[2]):
                self._select[1] = a.get("value", "")
        elif tag == "textarea" and a.get("name"):
            self._textarea = [a["name"], ""]

    def handle_data(self, data):
        if self._textarea is not None:
            self._textarea[1] += data

    def handle_endtag(self, tag):
        if tag == "form":
            self._form = None
        elif tag == "select" and self._select is not None:
            if self._select[1] is not None and self._form is not None:
                self._form["fields"].append([self._select[0], self._select[1]])
            self._select = None
        elif tag == "textarea" and self._textarea is not None:
            if self._form is not None:
                self._form["fields"].append(self._textarea)
            self._textarea = None


def find_form(page, **wanted):
    """The first form whose fields contain all `wanted` name=value pairs."""
    for form in Forms(page).forms:
        values = dict(form["fields"])
        if all(values.get(k) == v for k, v in wanted.items()):
            return form
    return None


class Session:
    def __init__(self, user=None, password="pass"):
        self.jar = os.path.join(WORK, f"cookies-{user or 'anon'}-{os.urandom(4).hex()}.txt")
        open(self.jar, "w").close()
        self.login_page = ""
        if user:
            _, body = self.post("/wp-login.php", [
                ("log", user), ("pwd", password), ("wp-submit", "Log In"), ("testcookie", "1"),
            ], cookie="wordpress_test_cookie=WP%20Cookie%20check")
            self.login_page = body

    def request(self, url, data=None, cookie=None, headers=None):
        """Returns (status, final URL, body). `data` is a list of (name, value) pairs."""
        header_file = os.path.join(WORK, "headers.txt")
        cmd = ["curl", "-s", "-L", "-c", self.jar, "-b", self.jar, "-D", header_file, "-o", "-",
               "-w", "\n%{url_effective}"]
        if cookie:
            cmd += ["--cookie", cookie]
        for header in headers or []:
            cmd += ["-H", header]
        for key, value in data or []:
            cmd += ["--data-urlencode", f"{key}={value}"]
        cmd.append(url if url.startswith("http") else BASE + url)
        out = subprocess.run(cmd, capture_output=True).stdout.decode(errors="replace")
        body, _, final = out.rpartition("\n")
        raw = open(header_file, encoding="latin-1", newline="").read()
        blocks = [b for b in raw.split("\r\n\r\n") if b.strip()]
        status = int(re.search(r"HTTP/[\d.]+ (\d+)", blocks[-1]).group(1)) if blocks else 0
        return status, final, body

    def get(self, url):
        status, _, body = self.request(url)
        return status, body

    def post(self, url, data, cookie=None):
        status, _, body = self.request(url, data=data, cookie=cookie)
        return status, body

    def submit(self, form, changes=None, button=None):
        """Sends a parsed form with some fields changed (None removes a field)."""
        changes = changes or {}
        data = [(k, v) for k, v in form["fields"] if k not in changes]
        for key, value in changes.items():
            for v in (value if isinstance(value, list) else [value]):
                if v is not None:
                    data.append((key, v))
        if button:
            data.append(button)
        return self.post(html.unescape(form["action"]), data)

    def logged_in(self):
        _, page = self.get(PAGES["orchestra_portal"])
        return "Hello " in page


admin_id, organizer_id, conductor_id = user_id("admin"), user_id("organizer"), user_id("conductor")
member_id, member2_id, sub_id = user_id("member"), user_id("member2"), user_id("sub")
PAGES = json.loads(wp_eval(
    'echo json_encode(array_combine($t = ["orchestra_portal", "orchestra_login", "orchestra_members", "orchestra_profile",'
    ' "orchestra_email"], array_map(fn ($s) => Orchestra\\Core\\Ui\\Pages::url($s), $t)));'
))
MEMBERS, PROFILE, EMAIL = PAGES["orchestra_members"], PAGES["orchestra_profile"], PAGES["orchestra_email"]


def member_url(uid):
    return f"{MEMBERS}&orchestra_member={uid}"


def rest_me(login, password):
    token = base64.b64encode(f"{login}:{password}".encode()).decode()
    status, _, _ = Session().request("/?rest_route=/wp/v2/users/me", headers=[f"Authorization: Basic {token}"])
    return status


check(all(PAGES.values()), "the Orchestra pages exist")

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

# --- Login and the dashboard ----------------------------------------------------------------
member = Session("member")
check(member.logged_in(), "active member can log in")
_, final, page = Session("member").request("/wp-admin/")
check(final.rstrip("/") == PAGES["orchestra_portal"].rstrip("/") and "Hello Mia" in page,
      "members are sent from wp-admin to the Orchestra pages")
_, final, _ = member.request("/wp-admin/profile.php")
check("profile.php" not in final, "members cannot open profile.php")
check('id="wpadminbar"' not in page, "members see no admin toolbar")
_, page = Session("admin", "admin").get(PAGES["orchestra_portal"])
check('id="wpadminbar"' in page, "administrators keep the admin toolbar")
_, final, _ = Session("admin", "admin").request("/wp-admin/")
check("/wp-admin/" in final, "administrators keep wp-admin")
_, page = member.get(PAGES["orchestra_portal"])
check("Members" in text(page) and "My profile" in text(page) and "Email" not in text(page).split("Hello", 1)[1],
      "the start page lists the areas the member can use")

_, page = Session().get(PAGES["orchestra_login"])
login_form = find_form(page, orchestra_login_page=PAGES["orchestra_login"]) or (Forms(page).forms or [None])[0]
check(login_form is not None and 'name="log"' in page, "the login page shows a login form")
visitor = Session()
_, page = visitor.get(PAGES["orchestra_login"] + "?redirect_to=" + urllib.parse.quote(PROFILE, safe=""))
form = next((f for f in Forms(page).forms if "log" in dict(f["fields"])), None)
_, final, page = visitor.request(html.unescape(form["action"]), [
    *((k, v) for k, v in form["fields"] if k not in ("log", "pwd")), ("log", "member"), ("pwd", "pass"),
], cookie="wordpress_test_cookie=WP%20Cookie%20check")
check(final.rstrip("/") == PAGES["orchestra_portal"].rstrip("/") and "Hello Mia" in page,
      "logging in on the login page leads to the start page, not the requested page")

# --- Members edit their own profile on the profile page ------------------------------------
_, page = member.get(PROFILE)
form = find_form(page, action="orchestra_members_save_profile")
check(form is not None and "Dietary restrictions" in page and "New password" in page,
      "the profile page shows account and orchestra fields")
_, page = member.submit(form, {"orchestra_members_financial_support": "1",
                               "orchestra_members_dietary_restrictions": "Vegetarian\nno nuts",
                               "display_name": "Mia Muster"})
check("Your profile has been saved." in text(page), "member saves the profile")
check(meta(member_id, "orchestra_members_financial_support") == "1", "member saves financial support")
check(meta(member_id, "orchestra_members_dietary_restrictions") == "Vegetarian\nno nuts", "member saves dietary restrictions")
check(wp("user", "get", "member", "--field=display_name") == "Mia Muster", "member changes their name")
_, page = member.get(PROFILE)
form = find_form(page, action="orchestra_members_save_profile")
_, page = member.submit(form, {"orchestra_members_dietary_restrictions": "x" * 1001})
check("must not be longer than 1000 characters" in text(page), "too long dietary restrictions are rejected with a message")
check(meta(member_id, "orchestra_members_dietary_restrictions") == "Vegetarian\nno nuts", "rejected input is not saved")
status, _ = member.submit(form, {"orchestra_members_dietary_restrictions": "forged", "_wpnonce": "invalid"})
check(status == 403 and meta(member_id, "orchestra_members_dietary_restrictions") == "Vegetarian\nno nuts",
      "profile data is not saved without a valid nonce")
_, page = member.submit(form, {"email": "mia@example.org"})
check("current password" in text(page) and wp("user", "get", "member", "--field=user_email") == "member@example.com",
      "changing the email address needs the current password")
_, page = member.submit(form, {"email": "mia@example.org", "current_password": "pass"})
check(wp("user", "get", "member", "--field=user_email") == "mia@example.org", "member changes their email address")
member.submit(form, {"email": "member@example.com", "current_password": "pass"})

member2 = Session("member2")
_, page = member2.get(PROFILE)
form = find_form(page, action="orchestra_members_save_profile")
member2.submit(form, {"orchestra_members_dietary_restrictions": "Gluten-free"})
check(meta(member2_id, "orchestra_members_dietary_restrictions") == "Gluten-free", "second member saves profile")
_, page = member2.get(PROFILE)
form = find_form(page, action="orchestra_members_save_profile")
_, page = member2.submit(form, {"new_password": "a-new-secret", "new_password_repeat": "a-new-secret",
                                "current_password": "pass"})
check(Session("member2", "a-new-secret").logged_in() and not Session("member2", "pass").logged_in(),
      "member changes their password")
member2 = Session("member2", "a-new-secret")
check(member2.logged_in(), "member stays logged in after changing the password")

sub = Session("sub")
_, page = sub.get(PROFILE)
check("Dietary restrictions" not in page, "users without an orchestra role have no orchestra profile")

# --- Member list and profile visibility -----------------------------------------------------
_, page = member.get(MEMBERS)
check(all(n in page for n in ["Olga", "Carl", "Max", "Mia"]), "members see the member list")
check("member2@example.com" not in page and "Gluten-free" not in page, "members only see names in the list")
check(re.search(r">\s*(Sam|admin)\s*<", page) is None, "list contains only orchestra users")
_, page = member.get(member_url(member_id))
check("Vegetarian" in page, "members can view their own profile")
_, page = member.get(member_url(member2_id))
check("not allowed to view this profile" in page and "Gluten-free" not in page, "members cannot view other profiles")
conductor = Session("conductor")
_, page = conductor.get(member_url(member2_id))
check("not allowed to view this profile" in page, "conductors cannot view other profiles")
_, page = sub.get(MEMBERS)
check("not allowed to see the members" in page, "users without an orchestra role cannot see the member list")
_, page = Session().get(MEMBERS)
check("Please log in" in page, "visitors are asked to log in")

organizer = Session("organizer")
_, page = organizer.get(MEMBERS)
check("member2@example.com" in page and "Active" in page, "organizers see emails and status")
_, page = organizer.get(member_url(member2_id))
check("Gluten-free" in page, "organizers can view other profiles")
check("Set inactive" in page, "organizers see the status form")
_, page = organizer.get(member_url(admin_id))
check("Set inactive" not in page, "administrators have no status form")
_, page = organizer.get(member_url(organizer_id))
check("Set inactive" not in page, "organizers cannot change their own status")

admin = Session("admin", "admin")
_, page = admin.get("/wp-admin/admin.php?page=orchestra-members")
check("member2@example.com" in page and "orchestra--admin" in page, "administrators see the members in wp-admin")
_, page = admin.get("/wp-admin/users.php")
check("Orchestra status" in page, "user list shows the orchestra status column")

# --- Status changes are protected -----------------------------------------------------------
_, page = organizer.get(member_url(member2_id))
form = find_form(page, action="orchestra_members_set_status")
status, _ = member.submit(form)
check(status == 403 and meta(member2_id, "orchestra_members_inactive") == "", "members cannot change a status")
organizer.submit(form, {"_wpnonce": "invalid"})
check(meta(member2_id, "orchestra_members_inactive") == "", "status change needs a valid nonce")
admin_form = dict(form, fields=[[k, str(admin_id) if k == "member" else v] for k, v in form["fields"]])
organizer.submit(admin_form)
check(meta(admin_id, "orchestra_members_inactive") == "", "administrators cannot be set inactive")

# --- Deactivation ---------------------------------------------------------------------------
member_app_password = wp("user", "application-password", "create", "member", "e2e", "--porcelain")
check(rest_me("member", member_app_password) == 200, "active member can use an application password")

_, page = organizer.get(member_url(member_id))
_, page = organizer.submit(find_form(page, action="orchestra_members_set_status"))
check("The member is inactive" in text(page), "organizer sets a member inactive on the members page")
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
visitor = Session()
_, page = visitor.get(PAGES["orchestra_login"])
form = Forms(page).forms[0]
_, page = visitor.submit(form, {"log": "member", "pwd": "pass"})
check(INACTIVE_MESSAGE in page and "orchestra-login" in page, "the login page shows the inactive message itself")

_, page = organizer.get(MEMBERS)
check("Mia" in page and "Inactive" in page, "organizers see inactive members")
_, page = member2.get(MEMBERS)
check("Mia" not in page, "members do not see inactive members")
_, page = admin.get(f"/wp-admin/user-edit.php?user_id={member_id}")
check("is deleted. It can be entered again" in page and "disabled" in page,
      "the profile of an inactive member cannot be filled in")

# --- Email to the global groups (orchestra-core's email service) -----------------------------
LOG = f"{EMAIL}&orchestra_view=log"


def mails():
    return json.loads(wp_eval('echo json_encode(get_option("e2e_mails", []));'))


def compose(session, groups, subject, body, op):
    """Writes an email on the email page; op "review" shows the review, "send" also sends it."""
    _, page = session.get(EMAIL)
    form = find_form(page, op="review")
    if form is None:
        return ""
    _, page = session.submit(form, {"groups[]": groups, "subject": subject, "body": body})
    if op == "review":
        return page
    send = find_form(page, action="orchestra_core_email_send")
    return session.submit(send)[1] if send else page


def log_rows():
    return json.loads(wp_eval(
        'global $wpdb; echo json_encode($wpdb->get_results("SELECT id, subject, body, recipients, sent, failed, status'
        ' FROM {$wpdb->prefix}orchestra_core_email_log ORDER BY id", ARRAY_A));'
    ))


# Only emails sent from here on count; changing the profile above sent notifications.
wp("option", "delete", "e2e_mails")
_, page = member2.get(EMAIL)
check("not allowed to send emails" in page, "members cannot write emails")
_, page = member2.get(PAGES["orchestra_portal"])
check("Email" not in text(page).split("Hello", 1)[1], "members have no email area")

_, page = organizer.get(EMAIL)
check(all(label in page for label in ["Everyone", "All members", "All conductors", "All organizers"]),
      "organizers see the global groups")
_, page = conductor.get(EMAIL)
check("All organizers" in page, "conductors may email the global groups")
_, page = organizer.get(EMAIL + "&groups%5B%5D=orchestra_members_conductors")
check(re.search(r'value="orchestra_members_conductors"\s+checked', page) is not None, "groups can be preselected by link")

page = compose(organizer, ["orchestra_members_everyone"], "", "Hello", "review")
check("Please enter a subject." in page, "the subject is required")
page = compose(organizer, [], "Rehearsal", "Hello", "review")
check("Please select at least one group." in page, "a group is required")
page = compose(organizer, ["orchestra_members_everyone"], "Rehearsal", "Hello all,\nsee you on Monday.", "review")
check("3 people" in page and "Max" in page and "Carl" in page and "Mia" not in page,
      "the review lists the recipients without inactive members")
check(len(mails()) == 0, "reviewing sends nothing")

wp("option", "update", "e2e_batch_size", "2")
page = compose(organizer, ["orchestra_members_everyone"], "Rehearsal", "Hello all,\nsee you on Monday.", "send")
check("is being sent to 3 recipients" in page and "Sent emails" in page, "sending shows the log on the email page")
sent = mails()
check(len(sent) == 2, "the first batch is sent right away")
check("Sending (2 of 3)" in page, "the log shows the progress")
wp("cron", "event", "run", "orchestra_core_email_send")
sent = mails()
rows = log_rows()
check(len(sent) == 3 and rows[-1]["status"] == "sent" and rows[-1]["sent"] == "3", "the rest is sent by WP-Cron")
check(rows[-1]["body"] is None, "the text is deleted after sending")
check(sorted(m["to"] for m in sent) == ["conductor@example.com", "member2@example.com", "organizer@example.com"],
      "one email per recipient, inactive members excluded")
first = sent[0]
check(first["subject"] == "Rehearsal" and first["message"].startswith("Hello all,\nsee you on Monday.")
      and "Sent by Olga" in first["message"] and "Orchestra: Everyone" in first["message"],
      "the email contains the message and who sent it to which group")
check(any(h.startswith("Reply-To:") and "organizer@example.com" in h for h in first["headers"]),
      "replies go to the sender")
_, page = organizer.get(LOG)
check("Rehearsal" in page and "Orchestra: Everyone" in page and ">Sent<" in page, "the log lists the email")
_, page = conductor.get(LOG)
check("Rehearsal" in page, "conductors see the log")
_, page = admin.get("/wp-admin/admin.php?page=orchestra-email-log")
check("Rehearsal" in page, "administrators see the log in wp-admin")

wp_eval(f'wp_update_user(["ID" => {conductor_id}, "user_email" => "fail@example.com"]);')
compose(organizer, ["orchestra_members_conductors", "orchestra_members_organizers"], "Board", "Meeting", "send")
for _ in range(2):
    wp("cron", "event", "run", "orchestra_core_email_send")
rows = log_rows()
check(rows[-1]["status"] == "sent" and rows[-1]["sent"] == "1" and rows[-1]["failed"] == "1",
      "failed deliveries are retried and then counted as failed")
wp_eval(f'wp_update_user(["ID" => {conductor_id}, "user_email" => "conductor@example.com"]);')

status, _ = member2.post(ADMIN_POST, [("action", "orchestra_core_email_send"), ("groups[]", "orchestra_members_everyone"),
                                      ("subject", "Spam"), ("body", "Spam"), ("_wpnonce", "x")])
check(status == 403 and len(log_rows()) == 2, "members cannot send emails")

wp_eval('global $wpdb; $wpdb->query("UPDATE {$wpdb->prefix}orchestra_core_email_log SET created_at = \'2020-01-01 00:00:00\''
        ' WHERE subject = \'Board\'");')
wp("cron", "event", "run", "orchestra_core_email_purge")
check([r["subject"] for r in log_rows()] == ["Rehearsal"], "log entries are deleted after 12 months")

# --- Reactivation ---------------------------------------------------------------------------
_, page = organizer.get(member_url(member_id))
_, page = organizer.submit(find_form(page, action="orchestra_members_set_status"))
check("active again" in text(page) and meta(member_id, "orchestra_members_inactive") == "",
      "organizer reactivates a member")
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
_, page = admin.get("/wp-admin/options-privacy.php?tab=policyguide")
check("Orchestra Members" in page and "Inactive members cannot log in." in page, "suggests a privacy policy text")

# --- Administrators choose the profile fields -----------------------------------------------
FIELDS = MEMBERS + ("&" if "?" in MEMBERS else "?") + "orchestra_members_view=fields"
_, page = admin.get(MEMBERS)
check("orchestra_members_view=fields" in page, "administrators find the profile fields on the members page")
_, page = organizer.get(MEMBERS)
check("orchestra_members_view=fields" not in page, "organizers do not see the profile fields link")
_, page = organizer.get(FIELDS)
check(find_form(page, action="orchestra_members_save_profile_fields") is None,
      "organizers cannot open the profile fields")
_, page = admin.get(FIELDS)
fields_form = find_form(page, action="orchestra_members_save_profile_fields")
check(fields_form is not None and "Financial support" in page, "administrators see the current profile fields")
check(["orchestra_delete_profile_field", "financial_support"] in fields_form["buttons"]
      and "data-orchestra-sortable" in page and "data-orchestra-drag-handle" in page,
      "every field has a delete button and a drag handle")
status, _ = organizer.submit(fields_form, button=("orchestra_delete_profile_field", "financial_support"))
check(status == 403 and "Financial support" in admin.get(FIELDS)[1], "organizers cannot change the profile fields")

wp("user", "meta", "update", str(member_id), "orchestra_members_financial_support", "1")
member = Session("member")
_, page = admin.submit(fields_form, {
    "orchestra_profile_fields[dietary_restrictions][label]": "Diet",
    "orchestra_new_profile_fields[0][label]": "Voice",
    "orchestra_new_profile_fields[0][type]": "select",
    "orchestra_new_profile_fields[0][choices]": "Soprano\nAlto",
    # The order of the rows after dragging the new field to the top
    "orchestra_profile_field_order[]": ["new:0", "financial_support", "dietary_restrictions"],
}, button=("orchestra_delete_profile_field", "financial_support"))
check("The profile fields have been saved." in text(page), "administrator saves the profile fields")
check(meta(member_id, "orchestra_members_financial_support") == "",
      "deleting a field deletes what the members entered in it")
_, page = member.get(PROFILE)
check("Financial support" not in page and "Diet" in page and "Soprano" in page
      and page.index("Voice") < page.index("Diet"), "members see the chosen fields in the chosen order")
_, page = member.submit(find_form(page, action="orchestra_members_save_profile"), {
    "orchestra_members_voice": "Alto", "orchestra_members_dietary_restrictions": "Vegan"})
check(meta(member_id, "orchestra_members_voice") == "Alto"
      and meta(member_id, "orchestra_members_dietary_restrictions") == "Vegan", "members fill in the new fields")
_, page = member.submit(find_form(member.get(PROFILE)[1], action="orchestra_members_save_profile"),
                        {"orchestra_members_voice": "Tenor"})
check(meta(member_id, "orchestra_members_voice") == "", "only offered choices are saved")
_, page = admin.get("/wp-admin/admin.php?page=orchestra-members-fields")
check(find_form(page, action="orchestra_members_save_profile_fields") is not None and "Voice" in page,
      "the profile fields can also be changed in wp-admin")
_, page = admin.submit(find_form(admin.get(FIELDS)[1], action="orchestra_members_save_profile_fields"),
                       {"orchestra_new_profile_fields[0][label]": "Diet", "orchestra_new_profile_fields[0][type]": "text"})
stored = json.loads(wp("option", "get", "orchestra_members_profile_fields", "--format=json"))
check("used more than once" in text(page) and [f["id"] for f in stored] == ["voice", "dietary_restrictions"],
      "invalid profile fields are rejected with a message and not saved")

# --- Uninstall ------------------------------------------------------------------------------
_, page = organizer.get(member_url(member2_id))
organizer.submit(find_form(page, action="orchestra_members_set_status"))
wp("plugin", "deactivate", "orchestra-members")
check(Session("member2", "a-new-secret").logged_in(), "deactivating the plugin no longer blocks inactive members")
check(meta(member2_id, "orchestra_members_inactive") == "1", "deactivating the plugin keeps the data")
wp("plugin", "uninstall", "orchestra-members")
remaining = wp_eval('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE \'orchestra_members_%\'");')
check(remaining == "0", "uninstall removes all profile data")
check(wp_eval('var_export(get_option("orchestra_members_profile_fields"));') == "false", "uninstall removes the profile fields")

print()
if failures:
    print(f"{len(failures)} check(s) failed")
    sys.exit(1)
print("All checks passed")
