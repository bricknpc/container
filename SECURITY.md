# Security Policy

## Supported versions

| Version | Status |
| --- | --- |
| 0.1.x | Active |
| Older | Unsupported |

While the package is pre-1.0, only the latest release line receives fixes.

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/container/security/advisories/new)
form. Do not disclose vulnerabilities in public issues or pull requests.

Include the affected version or commit, PHP version, a minimal reproduction,
and the impact and conditions needed to trigger the issue. Maintainers will
acknowledge and assess the report. Confirmed fixes are published with an
advisory crediting the reporter unless they prefer otherwise.

## Scope

Report security issues in this package's code or development configuration, such as:

- the container resolving an entry, running a callable, or applying a binding, attribute, decorator, or callback other
  than the ones the application registered or declared;
- an exception message or context that exposes a value the application passed to a factory, a delegate, `make()`, or
  `call()`. Messages and context are meant to hold only identifiers, class names, and parameter names, and never repeat
  the message of an exception a factory or a delegate threw;
- a control character in an identifier that is not escaped in an exception message, and so can forge a line in a log.

### Identifiers and callables are trusted input

The container builds any instantiable class and calls any public method it is given. Identifiers, class names, and
callables passed to `get()`, `has()`, `make()`, `call()`, and every registration method, and the parameters given to
`make()` and `call()`, are trusted application input. An application that passes user input to them, such as a class
name taken from a route or a callable taken from a request, lets that user build arbitrary classes and call arbitrary
methods. Check such input against an allowlist first.

A report that depends on passing untrusted input to these methods describes that documented contract rather than a
vulnerability, and is out of scope. So is anything that depends on changing the application's own code, including its
attributes and its factories.

### Out of scope

Bugs in PHP or third-party dependencies should be reported upstream, and so should an issue in a
[delegate container](docs/delegates.md), whose entries this package returns as they are. Application code and the
sensitivity of data an application chooses to store are the application's responsibility.
