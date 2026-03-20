# Architecture: lightncandy

## Purpose
LightnCandy — a PHP Handlebars/Mustache compiler and renderer. Compiles Handlebars templates to PHP closures at build time, eliminating runtime template parsing overhead and producing fast, portable rendering functions.

## Directory Structure
```
src/
  Lightn_Candy.php     # Public API — compile($template, $options): string (PHP code)
  Compiler.php         # Orchestrates compilation: scans tokens, emits PHP code
  Parser.php           # Parses Handlebars/Mustache token structure from the template text
  Token.php            # Token type constants (open/close blocks, partials, expressions, etc.)
  Context.php          # Compilation context — current scope, helpers registry, flags
  Flags.php            # Bitmask constants controlling compiler behaviour
  Expression.php       # Resolves and compiles Handlebars expressions to PHP code
  Partial.php          # Compiles {{> partial}} includes and inline partials
  Encoder.php          # HTML encoding helpers used in generated code
  Exporter.php         # Serializes PHP values to code strings (for embedding in generated PHP)
  Validator.php        # Validates template syntax during compilation
  Runtime.php          # Runtime helper functions used by the compiled PHP closure
  Safe_String.php      # Value object marking strings as already-escaped (no double-escaping)
  loader.php           # Legacy PSR-0 autoloader shim
```

## Key Design Decisions
- **Compile-once, run-many** — `LightnCandy::compile()` returns a string of PHP code (a `function` definition). This string is saved/eval'd once and called many times without re-parsing.
- **Flag-based feature set** — `Flags::FLAG_*` bitmasks enable/disable Handlebars features (strict mode, `this` context, helper lookup, partial support, mustache compat) so the generated code is as lean as possible.
- **Pluggable helpers** — Handlebars block helpers and inline helpers are registered in the options array and compiled to PHP callable references in the generated closure.
- **Safe_String** — values already HTML-escaped are wrapped in `Safe_String` to prevent double-escaping when triple-stash (`{{{value}}}`) or `#each` produce nested output.

## Extension Points
- Register custom helpers via the `helpers` option key to `compile()`.
- Register partial templates via the `partials` option key (or a `partialLoader` callable).
- Use flags (`FLAG_HANDLEBARSJS`, `FLAG_RUNTIMEPARTIAL`, etc.) to tune compatibility.

## Dependency Flow
```
LightnCandy::compile($template, $options)
  ├─ Parser::scan($template) → token stream
  ├─ Compiler::compileTokens(tokens, Context) → PHP code string
  │    ├─ Expression::compileExpression() → PHP value access code
  │    ├─ Partial::compilePartial() → PHP inline code
  │    └─ Validator::check() — syntax validation
  └─ returns PHP function definition string

$renderer = LightnCandy::prepare($phpCode);
$output   = $renderer($data); // executes the generated PHP closure
```
