CHANGELOG for 2.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.3 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.3.0...v2.3.1

* 2.3.4 (2013-08-27)

 * f936b41: clearToken exception is thrown at wrong place.
 * ea480bd: [Form] Fixed Form::all() signature for PHP 5.3.3
 * e1f40f2: [Locale] Fixed: Locale::setDefault() throws no exception when "en" is passed
 * d0faf55: [Locale] Fixed: StubLocale::setDefault() throws no exception when "en" is passed
 * 566d79c: [Yaml] fixed embedded folded string parsing
 * 33b0a17: [Validator] fixed Boolean handling in XML constraint mappings (closes #5603)
 * 0951b8d: [Translation] Fixed regression: When only one rule is passed to transChoice(), this rule should be used
 * 4563f1b: [Yaml] Fix comment containing a colon on a scalar line being parsed as a hash.
 * 7e87eb1: fixed request format when forwarding a request
 * 07d14e5: [Form] Removed exception in Button::setData(): setData() is now always called for all elements in the form tree during the initialization of the tree
 * ccaaedf: [Form] PropertyPathMapper::mapDataToForms() *always* calls setData() on every child to ensure that all *_DATA events were fired when the initialization phase is over (except for virtual forms)
 * 00bc270: [Form] Fixed: submit() reacts to dynamic modifications of the form children
 * c4636e1: added a functional test for locale handling in sub-requests
 * 05fdb12: Fixed issue #6932 - Inconsistent locale handling in subrequests
 * b3c3159: fixed locale of sub-requests when explicitely set by the developer (refs #8821)
 * 9bb7a3d: fixed request format of sub-requests when explicitely set by the developer (closes #8787)
 * fa35597: Sets _format attribute only if it wasn't set previously by the user.
 * f946108: fixed the format of the request used to render an exception
 * 51022c3: Fix typo in the check_path validator
 * 5f7219e: added a missing use statement (closes #8808)
 * 262879d: fix for Process:isSuccessful()
 * 0723c10: [Process] Use a consistent way to reset data of the process latest run
 * 85a9c9d: [HttpFoundation] Fixed removing a nonexisting namespaced attribute.
 * 191d320: [Validation] Fixed IdentityTranslator to pass correct Locale to MessageSelector
 * c6ecd83: SwiftMailerHandler in Monolog bridge now able to react to kernel.terminate event
 * 99adcf1: {HttpFoundation] [Session] fixed session compatibility with memcached/redis session storage
 * ab9a96b: Fixes for hasParameterOption and getParameterOption methods of ArgvInput
 * dbd0855: Added sleep() workaround for windows php rename bug
 * c342715: [Form] Fixed: Added "validation_groups" option to submit button
 * fa01e6b: [Process] Fix for #8754 (Timed-out processes are successful)
 * 909fab6: [Process] Fix #8742 : Signal-terminated processes are not successful
 * fa769a2: [Process] Add more precision to Process::stop timeout
 * 3ef517b: [Process] Fix #8739
 * 572ba68: [TwigBridge] removed superflous ; when rendering form_enctype() (closes #8660)
 * 18896d5a: [Validator] fixed the wrong isAbstract() check against the class (fixed #8589)
 * e8e76ec: [TwigBridge] Prevent code extension to display warning
 * 96aec0f: Fix internal sub-request creation
 * 6ed0fdf: [Form] Moved auto_initialize option to the BaseType
 * e47657d: Make sure ContextErrorException is loaded during compile time errors
 * 98f6969: Fix empty process argument escaping on Windows
 * 1a73b44: added missing support for the new output API in PHP 5.4+
 * e0c7d3d: Fixed bug introduced in #8675
 * 0b965fb: made the filesystem loader compatible with Twig 2.0
 * 8fa0453: [Intl] Updated stubs to reflect ICU 51.2
 * 322f880: replaced deprecated Twig features
 * 48338fc: Ignore null value in comparison validators

* 2.3.3 (2013-08-07)

 * c35cc5b: added trusted hosts check
 * 6d555bc: Fixed metadata serialization
 * cd51d82: [Form] fixed wrong call to setTimeZone() (closes #8644)
 * 5c359a8: Fix issue with \DateTimeZone::UTC / 'UTC' for PHP 5.4
 * 85330a6: [Form] Fixed patched forms to be valid even if children are not submitted
 * cb5e765: [Form] Fixed: If a form is not present in a request, it is not automatically submitted
 * 97cbb19: [Form] Removed the "disabled" attribute from the placeholder option in select fields due to problems with the BlackBerry 10 browser
 * c138304: [routing] added ability for apache matcher to handle array values
 * 1bd45b3: [FrameworkBundle] fixed regression where the command might have the wrong container if the application is reused several times
 * b41cf82: [Validator] fixed StaticMethodLoader trying to invoke methods of abstract classes (closes #8589)
 * e5fba3c: [Form] fixes empty file-inputs get treated as extra field
 * 3553c71: return 0 if there is no valid data
 * 50d0727: [DependencyInjection] fixed regression where setting a service to null did not trigger a re-creation of the service when getting it
 * dc1fff0: The ignoreAttributes itself should be ignored, too.
 * ae7fa11: [Twig] fixed TwigEngine::exists() method when a template contains a syntax error (closes #8546)
 * 28e0709: [Validator] fixed ConstraintViolation:: incorrect when nested
 * 890934d: handle Optional and Required constraints from XML or YAML sources correctly
 * a2eca45: Fixed #8455: PhpExecutableFinder::find() does not always return the correct binary
 * 485d53a: [DependencyInjection] Fix Container::camelize to convert beginning and ending chars
 * 2317443: [Security] fixed issue where authentication listeners clear unrelated tokens
 * 2ebb783: fix issue #8499 modelChoiceList call getPrimaryKey on a non object
 * 242b318: [DependencyInjection] Add exception for service name not dumpable in PHP
 * d3eb9b7: [Validator] Fixed groups argument misplace for validateValue method from validator class

* 2.3.2 (2013-07-17)

 * bb59f40: Reverts JSON_NUMERIC_CHECK
 * 9c5f8c6: [Yaml] removed wrong comment removal inside a string block
 * 2dc1ee0: [HtppKernel] fixed inline fragment renderer
 * 06b69b8: fixed inline fragment renderer
 * 91bb757: ProgressHelper shows percentage complete.
 * 9d1004b: fix handling of a default 'template' as a string
 * 82dbaee: [HttpKernel] fixed the inline renderer when passing objects as attributes (closes #7124)
 * 8bb4e4d: [DI] Fixed bug requesting non existing service from dumped frozen container
 * 6dbd1e1: [WebProfiler] fix content-type parameter
 * a830001: Passed the config when building the Configuration in ConfigurableExtension
 * c875d0a: [Form] fixed INF usage which does not work on Solaris (closes #8246)
 * ab1439e: [Console] Fixed the table rendering with multi-byte strings.
 * c0da3ae: [Process] Disable exception on stream_select timeout
 * 77f2aa8: [HttpFoundation] fixed issue with session_regenerate_id (closes #7380)
 * bcbbb28: Throw exception if value is passed to VALUE_NONE input, long syntax
 * 6b71513: fixed date type format pattern regex
 * b5ded81: [Security] fixed usage of the salt for the bcrypt encoder (refs #8210)
 * 842f3fa: do not re-register commands each time a Console\Application is run
 * 0991cd0: [Process] moved env check to the Process class (refs #8227)
 * 8764944: fix issue where $_ENV contains array vals
 * 4139936: [DomCrawler] Fix handling file:// without a host
 * e65723c: fix-progressbar-start
 * aa79393: also consider alias in Container::has()
 * de289d2: [Form] corrected interface bind() method defined against in deprecation notice
 * 0c0a3e9: [Console] fixed regression when calling a command foo:bar if there is another one like foo:bar:baz (closes #8245)
 * 849f3ed: [Finder] Fix SplFileInfo::getContents isn't working with ssh2 protocol
 * 6d2135b: force the Content-Type to html in the web profiler controllers

* 2.3.1 (2013-06-11)

 * 25e3abd: fix many-to-many Propel1 ModelChoiceList
 * bce6bd2: [DomCrawler] Fixed a fatal error when setting a value in a malformed field name.
 * e3561ce: [FrameworkBundle] Fixed OutOfBoundException when session handler_id is null
 * 81b122d: [DependencyInjection] Add support for aliases of aliases + regression test
 * 445b2e3: [Console] fix status code when Exception::getCode returns something like 0.1
 * bbfde62: Fixed exit code for exceptions with error code 0
 * d8c0ef7: [DependencyInjection] Rename ContainerBuilder::$aliases to avoid conflicting with the parent class
 * bb797ee: [DependencyInjection] Remove get*Alias*Service methods from compiled containers
 * 379f5e0: [DependencyInjection] Fix aliased access of shared services, fixes #8096
 * afad9c7: instantiate valid commands only

* 2.3.0 (2013-06-03)

 * e93fc7a: [FrameworkBundle] set the dispatcher in the console application
 * 2038329: [Form] [Validator] Fixed post_max_size = 0 bug (Issue #8065)
 * 554ab9f: [Console] renamed ConsoleForExceptionEvent into ConsoleExceptionEvent
 * fd151fd: [Security] Fixed the check if an interface exists.
 * c8e5503: [FrameworkBundle] removed HttpFoundation classes from HttpKernel cache
 * 169c0b9: [Finder] Fix iteration fails with non-rewindable streams
 * 45b68e0: [Finder] Fix unexpected duplicate sub path related AppendIterator issue
 * 13ba4ea: fix logger in regards to DebugLoggerInterface
 * 97b38ed: Added type of return value in VoterInterface.
 * 79a842a: [Console] Add namespace support back in to list command
 * 5321600: Fixed two bugs in HttpCache
 * 435012f: [Config] Adding the previous exception message into the FileLoaderLoadException so it's more easily seen
 * 5c317b7: [Console] fix and refactor exit code handling
 * 1469953: [CssSelector] Fix :nth-last-child() translation
 * 2d9027d: [CssSelector] Fix :nth-last-child() translation
 * 91b8490: Fix Crawler::children() to not trigger a notice for childless node

* 2.3.0-RC1 (2013-05-16)

 * 95f356b: remove check for PHP bug #50731
 * 8f54da7: [BrowserKit] should not follow redirects if status code is not 30x
 * f41ac06: changed all version deps to accepts all upcoming Symfony versions
 * a4e3ebf: [DomCrawler] Fixed the Crawler::html() method for PHP versions earlier than 5.3.6.
 * 3beaf52: [Security] Disabled the BCryptPasswordEncoder tests for PHP versions lower than 5.3.7.

* 2.3.0-BETA2 (2013-05-10)

 * 97bee20: Pass exceptions from the ExceptionListener to Monolog
 * be42dbc: [HttpFoundation][File][UploadedFile] Fix guessClientExtension() method
 * a5441b2: Fixed parsing of leading blank lines in folded scalars. Closes #7989.
 * e8d5d16: Fixed Loader import
 * bd0c48c: [Console] moved the IO configuration to its own method
 * fdb4b1f: [Console] moved --help support to allow proper behavior with other passed options
 * dd0e138: Eased translationNodeVisitor overriding in TranslationExtension
 * 853f681: fixed request scope issues (refs #7457)
 * 60edc58: Fixed fatal error in normalize/denormalizeObject.
 * 78e3710: ProxyManager Bridge
 * 41805c0: [Crawler] Add proper validation of node argument of method add
 * 7933971: [Form] Added radio button for empty value to expanded single-choice fields
 * 0586c7e: made some optimization when parsing YAML files
 * 1856df3: [Security] fixed wrong merge (refs #4776)
 * 5b7e1e6: added a missing check for the provider key
 * f1c2ab7: [DependencyInjection] Add a method map to avoid computing method names from service names
 * ea633f5: [HttpKernel] Avoid updating the context if the request did not change
 * 997d549: [HttpFoundation] Avoid a few unnecessary str_replace() calls
 * f5e7f24: [HttpFoundation] Optimize ServerBag::getHeaders()
 * 59b78c7: [Validator] Fixed: $traverse and $deep is passed to the visitor from Validator::validate()
 * bcb5400: [Form] Fixed transform()/reverseTransform() to always throw TransformationFailedExceptions
 * 7b2ebbf: [Form] Fixed: String validation groups are never interpreted as callbacks
 * 0610750: if the repository method returns an array ensure that it's internal poin...
 * dcced01: [Form] Improved multi-byte handling of NumberToLocalizedStringTransformer
 * 90a20d7: [Translation] Made translation domain defaults in Translator consistent with TranslatorInterface
 * 549a308: [Form] Fixed CSRF error messages to be translated and added "csrf_message" option
