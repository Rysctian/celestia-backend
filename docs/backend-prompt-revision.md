Refine the existing `BACKEND_CODING_GUIDELINES.md` to make it more concise, focused, and reusable for future backend development.

My primary goal is to document my personal coding structure, conventions, abstraction patterns, and development habits based on the existing codebase.

Keep the following:
- Folder organization and separation of responsibilities.
- Controller, service, model, query trait, and FormRequest patterns.
- Naming conventions and method structures.
- Database operations, relationships, and transaction patterns.
- Validation, authorization, and API response conventions.
- Reusability, readability, and simplicity principles.
- Short examples and references to existing implementations.

Move codebase bugs, incomplete implementations, inconsistencies, and other temporary audit findings into `codex/BACKEND_REVIEW_FINDINGS.md`.

Keep specialized business logic out of the general coding guidelines unless it demonstrates a broadly reusable coding pattern.

Target approximately 100–200 meaningful lines for the main guidelines, without sacrificing important conventions.

Important:
- Do not modify existing application code.
- Do not invent new architectural patterns.
- Do not blindly turn inconsistent or problematic code into coding standards.
- Preserve my established coding style and preference for minimal abstractions and shallow folder structures.
- Make every guideline actionable for Codex.
- Keep the Markdown properly formatted and easy to navigate.
- Ensure file references remain accurate.

The final document should help Codex consistently implement new features and major backend changes in my existing coding style.