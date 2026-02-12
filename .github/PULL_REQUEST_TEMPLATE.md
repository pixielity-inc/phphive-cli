# ============================================================================
# Pull Request Template - Mono CLI Tool
# ============================================================================
#
# This template helps contributors create well-structured pull requests
# with all necessary information for reviewers.
#
# Instructions:
#   - Fill out all relevant sections
#   - Delete sections that don't apply
#   - Link related issues using #issue_number
#   - Mark checkboxes with [x]
#
# ============================================================================

## Description

<!-- 
Provide a clear and concise description of what this PR does.
Explain the motivation and context for the changes.
-->

### Summary


### Motivation


## Type of Change

<!-- Mark all relevant options with an "x" -->

- [ ] 🐛 Bug fix (non-breaking change which fixes an issue)
- [ ] ✨ New feature (non-breaking change which adds functionality)
- [ ] 💥 Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] 📝 Documentation update (changes to README, docs, or inline documentation)
- [ ] 🎨 Code style update (formatting, renaming, no functional changes)
- [ ] ♻️ Refactoring (no functional changes, no API changes)
- [ ] ⚡ Performance improvement (faster execution, reduced memory usage)
- [ ] ✅ Test update (adding or updating tests)
- [ ] 🔧 Build/CI update (changes to build process or CI configuration)
- [ ] 🔒 Security fix (fixes a security vulnerability)
- [ ] 📦 Dependency update (updates to composer.json or package.json)

## Related Issues

<!-- 
Link to related issues using keywords:
  - Closes #123 (automatically closes the issue when PR is merged)
  - Fixes #123 (same as Closes)
  - Resolves #123 (same as Closes)
  - Related to #123 (doesn't close the issue)
-->

Closes #

## Changes Made

<!-- 
List the main changes made in this PR.
Be specific about what was added, modified, or removed.
-->

### Added
- 

### Changed
- 

### Removed
- 

### Fixed
- 

## Command Changes

<!-- If this PR adds or modifies commands, document them here -->

### New Commands
<!-- Example: `mono test:changed` - Run tests only in changed packages -->

### Modified Commands
<!-- Example: `mono test` - Added `--parallel` option for faster execution -->

### Deprecated Commands
<!-- Example: `mono old-command` - Use `mono new-command` instead -->

## Breaking Changes

<!-- 
If this PR includes breaking changes, describe them here.
Include migration instructions for users.
-->

### What breaks?


### Migration guide


## Testing

<!-- Describe how you tested these changes -->

### Test Environment
- PHP Version: 
- OS: 
- Composer Version: 

### Tests Performed

- [ ] All existing tests pass
- [ ] New tests added for new functionality
- [ ] Manual testing performed
- [ ] Tested on multiple PHP versions (8.4, 8.5)
- [ ] Tested on multiple operating systems

### Test Commands Run

```bash
# Add the commands you ran to test your changes
composer test
composer lint
composer typecheck
composer refactor:dry
```

### Test Results

<!-- Paste relevant test output or describe results -->

```
# Test output here
```

## Code Quality

<!-- Confirm all quality checks pass -->

- [ ] Code follows PSR-12 coding standards
- [ ] Pint formatting passes (`composer lint`)
- [ ] PHPStan analysis passes (`composer typecheck`)
- [ ] Rector refactoring check passes (`composer refactor:dry`)
- [ ] All tests pass (`composer test`)
- [ ] No new PHPStan errors introduced
- [ ] Code coverage maintained or improved

## Documentation

<!-- Confirm documentation is updated -->

- [ ] README.md updated (if applicable)
- [ ] CHANGELOG.md updated with changes
- [ ] Inline code documentation (docblocks) added/updated
- [ ] Command help text added/updated
- [ ] Examples added for new features
- [ ] Migration guide added for breaking changes

## Checklist

<!-- Mark completed items with an "x" -->

### Code Quality
- [ ] My code follows the project's code style guidelines
- [ ] I have performed a self-review of my code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have added comprehensive docblocks to all classes and methods
- [ ] My changes generate no new warnings or errors

### Testing
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
- [ ] I have tested the changes manually
- [ ] I have tested edge cases and error conditions

### Documentation
- [ ] I have made corresponding changes to the documentation
- [ ] I have updated the CHANGELOG.md file
- [ ] I have updated command help text (if applicable)
- [ ] I have added examples for new features

### Dependencies
- [ ] Any dependent changes have been merged and published
- [ ] I have updated composer.json if dependencies changed
- [ ] I have run `composer update` and committed composer.lock

### Git
- [ ] My commits follow the conventional commit format
- [ ] I have rebased my branch on the latest main/develop
- [ ] I have resolved all merge conflicts
- [ ] My branch has a descriptive name

## Screenshots / Recordings

<!-- 
Add screenshots or screen recordings to demonstrate the changes.
Especially useful for CLI output, new commands, or UI changes.
-->

### Before


### After


## Performance Impact

<!-- 
Describe any performance implications of this PR.
Include benchmarks if applicable.
-->

- [ ] No performance impact
- [ ] Performance improved
- [ ] Performance degraded (explain why this is acceptable)

### Benchmarks

<!-- Add benchmark results if applicable -->

```
# Before:
# After:
```

## Security Considerations

<!-- 
Describe any security implications of this PR.
Have you considered potential security vulnerabilities?
-->

- [ ] No security implications
- [ ] Security improved
- [ ] Potential security concerns (describe below)

### Security Notes


## Deployment Notes

<!-- 
Any special considerations for deploying this change?
Does it require configuration changes, migrations, or manual steps?
-->

- [ ] No special deployment steps required
- [ ] Requires configuration changes (describe below)
- [ ] Requires manual steps (describe below)

### Deployment Steps


## Additional Notes

<!-- 
Add any additional notes, context, or information that reviewers should know.
This could include:
  - Design decisions and trade-offs
  - Alternative approaches considered
  - Known limitations
  - Future improvements planned
  - Questions for reviewers
-->

## Reviewer Checklist

<!-- For reviewers - do not fill this out as a contributor -->

- [ ] Code quality and style are acceptable
- [ ] Tests are comprehensive and pass
- [ ] Documentation is clear and complete
- [ ] No security concerns identified
- [ ] Performance impact is acceptable
- [ ] Breaking changes are justified and documented
- [ ] CHANGELOG.md is updated appropriately
