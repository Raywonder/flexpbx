# Flex PBX Post Major Update Checklist

After every major code update, deployment, installer rebuild, or production configuration change, run this checklist before calling the update complete.

## Required Auth And Signup Checks

- Confirm new signup submission, administrator approval, welcome email, password setup, user portal login, and Flex Phone login all use the same account record.
- Confirm user-facing links use production PBX hosts such as `pbx.tappedin.fm` or `pbx.devinecreations.net`; do not send normal clients to `flexpbx.devinecreations.net` unless the test is explicitly for the dev backend.
- Confirm welcome and approval emails do not include raw SIP passwords, API tokens, reset tokens in logs, or other secrets.
- Confirm password reset writes PHP `password_hash()` values that the login code can verify.
- Confirm root webroot files and `src/` packaging files have the same auth behavior before building installers or server packages.
- Confirm Flex Phone can find users by email, username, and extension after approval.
- Confirm any exposed or pasted credential is treated as compromised and rotated or superseded where it exists.

## Required Deployment Checks

- Run `php -l` on every changed PHP file in the live runtime and in the matching `src/` path.
- Smoke-test `GET` for login, forgot-password, reset-password, Flex Phone link, and relevant API endpoints on both production domains.
- Smoke-test a bad password response and confirm it is generic, not a false "user does not exist" routing failure.
- Check mail logs for delivery handoff after any user-facing onboarding or correction email.
- Update source control after live deployment: commit the source changes, push to the server-hosted Git remote, and push to GitHub unless a documented blocker prevents it.
- Do not stage unrelated binary installer archives or unrelated dirty worktree files as part of an auth hotfix.

## Required Support Notes

- If a real client account was changed, send a no-secret correction or setup email when needed.
- Record the active username, extension, production server, and support expectation in the support ticket or internal note.
- Never copy raw passwords, SIP secrets, reset tokens, or API keys into tickets, chat, commits, or documentation.
