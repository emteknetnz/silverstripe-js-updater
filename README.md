# Silverstripe JS Updater

This tool updates JavaScript files in Silverstripe modules. It is intended to be run on a developer's laptop to create pull requests in GitHub.

**This tool is only intended for use by Silverstripe core committers or the Silverstripe Ltd CMS Squad**

It will update [supported-modules](https://github.com/silverstripe/supported-modules) within the current project for the current branches those modules are on.

## GitHub Token

This tool creates pull requests via the GitHub API. You need to set the `GITHUB_TOKEN` environment variable for this to work.

Create a new GitHub token at [https://github.com/settings/tokens/new](https://github.com/settings/tokens/new). Tick the `public_repo` checkbox and set the token to expire in 7 days. If you do not set the correct permissions, you will get a 404 error when attempting to create pull requests.

Delete this token once you have finished.

## Installation

Create a `silverstripe/recipe-kitchen-sink` project for the version of the CMS you want to update JS dependencies for e.g. `6`.

Then composer require this module.

```bash
composer require emteknetnz/silverstripe-js-updater
```

## Usage

Run this on your local machine so that you have access to nvm, yarn, git, etc. Do not run this inside a Docker container.

When updating JS, you must first run `update admin`, which will update the JS dependencies for `silverstripe/admin` and create a pull request. Once that pull request is green in CI, you can run `update others` to update all other modules in the project, some of which rely on shared components within `silverstripe/admin`.

## Commands:

### `show`

List all supported-modules within the current project

#### Command line arguments:

(none)

#### Command line options:

| Option | Description |
| -------| ------------|
| --github -g | Output as github urls |

**Example usage:**

```bash
vendor/bin/update-js show --github
```

### `update`

This command will update JS dependencies for supported modules within the project.

#### Command line arguments:

| Argument | Description |
| -------- | ------------|
| which | "admin" or "others" - whether to update `silverstripe/admin` or every other module that is not `silverstripe/admin` |
| githubIssueUrl | The GitHub URL of the parent issue |

#### Command line options:

| Option | Description |
| ------ | ------------|
| --only=[modules] -o | Only include the specified modules (without account prefix) separated by commas e.g. `silverstripe-config,silverstripe-assets` |
| --exclude=[modules] -e | Exclude the specified modules (without account prefix) separated by commas e.g. `silverstripe-mfa,silverstripe-totp` |
| --dry-run -d | Do not create pull requests |

**Example usage:**

```bash
GITHUB_TOKEN=abc123 vendor/bin/update-js update others --exclude=silverstripe-linkfield,silverstripe-mfa --dry-run
```

## License

[MIT](LICENSE)
