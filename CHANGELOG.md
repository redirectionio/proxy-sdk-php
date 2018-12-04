# ChangeLog

## 0.2.0

- Add match on response status code command
- Deprecate findRedirect and log methods on the client, use request with Command instead
- Introduce concept of commands and a new public method to send those command on redirection io agent
- Update protocol to match with the new one introduced in agent 1.3.0

## 0.1.4

- Fix fwrite reliability

## 0.1.3

- Add target in every log sent, even if redirection does not come from a rule
- Added the `ruleId` in the HTTP objects. It will be used to log everything

## 0.1.2

- Added more debugging features

## 0.1.1

- Added support for 410 HTTP status code

## 0.1.0

- Initial release
