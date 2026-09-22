#!/usr/bin/env python3
"""Shared master-login drivers for the HTTP suites.

Every sign-in starts at index.php?page=login (the master login): one email step
routes staff to the office dashboard and students to the student portal, while
modals handle account creation, recovery, inquiries and applicant tracking. The
portals keep no login screens of their own; logged-out visits redirect here.

Each suite Browser keeps its own cookie jar; Master drives flows through any
browser exposing .get(path, data) and leaves results in .html/.status.
"""
import re

LOGIN = 'index.php?page=login'
MASTER_MARK = 'ONE SIGN-IN FOR EVERYONE'


def csrf(html):
    token = re.search(r'name="csrf" value="([^"]+)"', html)
    assert token, 'master login CSRF token missing in response'
    return token.group(1)


class Master:
    def __init__(self, browser):
        self.b = browser

    def page(self, query=LOGIN):
        return self.b.get(query)

    def raw(self, query, data):
        return self.b.get(query, data)

    def form(self, action, query=LOGIN, **fields):
        html = self.page(query)
        return self.raw(query, dict(action=action, csrf=csrf(html), **fields))

    # Every "start" returns the step HTML; the caller reads the emailed code,
    # then passes that same HTML to the matching "verify" so the portal
    # session token chains correctly across steps.
    def otp_start(self, email, query=LOGIN):
        return self.form('otp_start', query, email=email)

    def otp_verify(self, html, code, query=LOGIN):
        return self.raw(query, dict(action='otp_verify', csrf=csrf(html), code=code))

    def password_login(self, html, email, password, query=LOGIN):
        return self.raw(query, dict(action='password_login', csrf=csrf(html), email=email, password=password))

    def applicant_start(self, email, query=LOGIN):
        return self.form('applicant_start', query, email=email)

    def applicant_verify(self, html, code, query=LOGIN):
        return self.raw(query, dict(action='applicant_verify', csrf=csrf(html), code=code))

    def create_start(self, query=LOGIN, **fields):
        return self.form('create_start', query, **fields)

    def create_verify(self, html, code, query=LOGIN):
        return self.raw(query, dict(action='create_verify', csrf=csrf(html), code=code))

    def recovery_start(self, email, query=LOGIN):
        return self.form('recovery_start', query, email=email)

    def recovery_verify(self, html, code, query=LOGIN):
        return self.raw(query, dict(action='recovery_verify', csrf=csrf(html), code=code))

    def inquiry(self, query=LOGIN, **fields):
        return self.form('inquiry_save', query, **fields)
