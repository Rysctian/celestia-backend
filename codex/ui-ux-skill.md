# Celestia HRIS UI/UX Guide

Use this as the default UI/UX direction for Celestia HRIS.

## Overall Style

Celestia HRIS must feel:

- modern
- professional
- minimalist
- clean
- enterprise-readySpec-Driven Development
- spacious
- easy to scan
- consistent across modules

Default visual direction:

- white surfaces
- very light gray page background
- dark neutral text
- blue primary accent
- subtle borders
- soft rounded corners
- very light or no shadows
- Geist / Geist Sans typography
- simple Lucide-style line icons

Avoid:

- crowded pages
- excessive cards
- large gradients
- heavy shadows
- oversized icons
- unnecessary charts
- decorative UI that does not help the workflow
- too many colors
- too many badges

## Application Layout

Use three main areas:

```text
Sidebar
Top Header
Main Content
```

### Sidebar

- white background
- subtle right border
- Celestia logo at the top
- grouped HRIS navigation
- collapsible parent modules
- active parent uses soft blue background
- active child uses light blue background and a small blue vertical indicator
- blue text/icon for active items
- keep navigation compact and readable

Example:

```text
Dashboard
Daily Time Record

Employee Database
  Employee 201 File

Administration
  User Management
  Payroll Cut-Off
```

Do not use a heavy dark sidebar unless specifically requested.

### Top Header

Keep it slim and minimal.

Use:

```text
Sidebar toggle
Breadcrumb
Theme control
Current user
```

Example breadcrumb:

```text
Employee Database > Employee 201 File
```

User area may contain:

- small avatar
- name
- email or role
- dropdown

Do not place many page actions in the top header.

## Page Header

Each main module should normally have:

```text
Page Title
Short description
Primary action on the right
```

Example:

```text
Employees
Keep your team directory up to date.

                              [ Add employee ]
```

Rules:

- clear direct page title
- short subtitle only
- one obvious primary action
- avoid multiple competing primary buttons

## Main Content Card

For table-based modules, prefer one large clean container.

```text
+------------------------------------------------------+
| Search / Filters                                     |
|------------------------------------------------------|
| Main Table                                           |
|                                                      |
|------------------------------------------------------|
| Pagination                                           |
+------------------------------------------------------+
```

Style:

- white background
- thin neutral border
- medium rounded corners
- little or no shadow
- comfortable padding

Do not wrap every small element in a separate card.

## Search and Filters

Filters are a core Celestia pattern.

Place search and filters directly above the table.

Example:

```text
[ Search employees... ] [ Type ] [ Gender ] [ Civil status ] [ Date added ]
```

### Search

- search field comes first
- use a search icon
- concise placeholder
- wide enough for normal queries
- use server-side search for large datasets

### Filter Buttons

Use compact filter buttons.

Examples:

```text
Type
Gender
Civil status
Date added
```

Style:

- compact height
- subtle gray background or outline
- small filter/calendar icon
- rounded corners
- never style normal filters like primary buttons

### Filter Dropdowns

For filters with many values, use a popover:

```text
Type

[ Find type... ]

[ ] Admin
[ ] Employee
[ ] Regular
[ ] Probationary
[ ] Contractual
[ ] Part-time
[ ] Intern
```

Rules:

- searchable when options are long
- multi-select uses checkboxes
- list becomes scrollable when needed
- do not use a full modal for simple table filters

### Active Filters

When filters are active:

- make the active filter recognizable
- optionally show selected count

Example:

```text
Type · 2
```

Show `Clear filters` only when filters are active.

Do not create excessive filter chips.

## Tables

Tables are one of the primary Celestia HRIS interfaces.

They must feel:

- clean
- readable
- spacious
- easy to scan
- not overly dense

### Table Appearance

Use:

- white background
- thin row separators
- subtle header background
- comfortable row height
- consistent spacing
- compact typography
- clear alignment

Avoid:

- heavy borders
- strong alternating row colors
- too many visible columns
- excessive badges

### Table Headers

Use muted medium-weight text.

Support sorting only where it makes sense.

Example columns:

```text
Employee
Employee ID
Type
Email
Gender
Civil status
Date added
```

Sort icons should be small and subtle.

### Employee Column

For employee lists:

```text
Avatar / Initials + Employee Name
```

Example:

```text
[SD] Sedrick Domenico Stroman
```

Use small circular avatars or initials.

### Category / Status Pills

Use subtle pills for values that benefit from quick recognition.

Example:

```text
Single
Married
Widowed
Separated
```

Pills should be:

- compact
- muted
- neutral unless semantic color is useful

Do not assign bright random colors to every category.

## Table Actions

Keep row actions compact.

Prefer:

```text
...
```

or small icon actions for:

- view
- edit
- archive/delete

Do not add multiple large text buttons to every row.

Permission behavior:

- no permission = hide action
- permission exists but action cannot currently proceed = disabled action

## Pagination

Use server-side pagination for large datasets.

Example:

```text
Showing 1-10 of 124 employees

Rows per page: 10

< 1 2 3 ... 13 >
```

Keep pagination compact and visually secondary to the table.

## Empty States

Never leave an empty table unexplained.

Example:

```text
No employees found.

Try changing your filters or add a new employee.
```

Small neutral icon is acceptable.

Avoid oversized illustrations.

## Loading States

Prefer:

- table skeleton rows
- compact loading state
- button spinner for submitted actions

Avoid full-screen blocking loaders for normal CRUD/table actions.

## Forms

Forms must be structured and easy to scan.

Use:

- clear labels
- consistent field heights
- concise helper text
- logical sections
- two-column layout when appropriate

Example:

```text
Personal Information

First name          Last name
Middle name         Name extension
Birth date          Gender
Civil status        Mobile number
```

For long employee forms, group fields by business meaning.

Do not build one giant unstructured form.

## Validation and Errors

Field validation should appear beside or below the related input.

Use concise messages.

Example:

```text
Email
[ input ]

Please enter a valid email address.
```

Use a toast or concise inline alert for server/business errors.

Do not rely only on a generic alert when a specific field caused the problem.

## Dialogs and Modals

Use dialogs for focused actions such as:

- create/edit small records
- assign roles
- edit schedule
- confirmation
- simple configuration

Dialog structure:

```text
Title
Short description
Form/content

Cancel                         Save
```

Rules:

- white surface
- clear title
- medium rounded corners
- primary action on the right
- secondary action beside it

If a workflow becomes large or complex, use a dedicated page instead of a huge modal.

## Buttons

### Primary

Use blue primary buttons for the main action.

Examples:

```text
Add employee
Save
Create
```

### Secondary

Use neutral/outline styling.

Examples:

```text
Cancel
Export
Reset
```

### Ghost

Use for low-emphasis controls.

Examples:

```text
More
Close
Row actions
```

Avoid multiple primary buttons competing on the same screen.

## Destructive Actions

Delete/archive actions must look destructive.

Example:

```text
Delete employee?

This action cannot be undone.

Cancel                         Delete
```

Use red only for the destructive action.

## Typography

Preferred font:

```text
Geist / Geist Sans
```

Use a simple hierarchy:

```text
Page title
Section title
Body
Muted description
Table label
```

Avoid:

- oversized headings
- excessive bold
- too many font sizes

## Spacing

Use consistent spacing throughout the system.

Principle:

```text
More space around major sections.
Less space inside compact controls.
```

Keep consistent:

- page padding
- card padding
- toolbar gaps
- form spacing
- table row height

Do not make the UI cramped.

## Colors

Direction:

```text
Page background   very light gray
Surface           white
Primary           blue
Text              dark neutral
Muted text        gray
Border            light gray
Active navigation soft blue
Danger            red
Success           green
Warning           amber
```

Use semantic colors only when they communicate meaning.

Do not make normal HRIS screens highly colorful.

## Icons

Use clean line icons such as Lucide.

Rules:

- small
- consistent stroke
- neutral by default
- only use when they improve recognition

Do not add icons to every text label.

## Responsive Behavior

Desktop is the primary HRIS experience.

On smaller screens:

- sidebar collapses
- filter toolbar may wrap
- table may scroll horizontally
- primary action stays accessible
- do not squeeze important columns until unreadable

Horizontal scrolling is acceptable for complex HRIS tables.

## Permissions

Respect access permissions in the UI.

Typical permissions:

```text
canView
canCreate
canUpdate
canDelete
```

Rules:

```text
No view permission   -> No Access page
No create permission -> Hide Add/Create
No update permission -> Hide Edit
No delete permission -> Hide Delete
```

Do not show forbidden actions disabled.

Disabled means the user has permission but the action is temporarily unavailable.

## Default Data Module Pattern

For modules such as:

- Employees
- Attendance
- Schedules
- Payroll Cut-Off
- User Management
- Roles and Permissions
- Leave
- Overtime

Prefer this structure:

```text
Page Title                           Primary Action
Short description

+-------------------------------------------------------+
| Search | Filter | Filter | Filter | Date Filter       |
|-------------------------------------------------------|
|                                                       |
| Main Data Table                                       |
|                                                       |
|-------------------------------------------------------|
| Pagination                                            |
+-------------------------------------------------------+
```

This should be the default Celestia HRIS page pattern unless the workflow requires something different.

## Recommended Table Capabilities

Add features only when they improve the HR workflow.

Recommended:

- server-side search
- server-side filters
- sorting
- pagination
- loading state
- empty state
- row actions

Optional when required:

- bulk selection
- column visibility
- export

Do not add complex table functionality just because the library supports it.

## UI Consistency Rules

When adding a new module:

1. Reuse existing Celestia components before creating new patterns.
2. Keep the same page header structure.
3. Keep the same table/filter style.
4. Keep the same button hierarchy.
5. Keep spacing and typography consistent.
6. Do not introduce a different design language for one module.
7. Prefer simplicity when two designs provide the same functionality.

## Do

- keep pages focused
- use whitespace
- keep filters compact
- use searchable multi-select filters for large option sets
- keep tables readable
- use subtle badges
- make primary actions obvious
- use consistent CRUD patterns
- respect permissions
- prioritize fast HR workflows

## Do Not

- overload pages with information
- add unnecessary dashboards/charts
- create many nested cards
- use large decorative headings
- use gradients without a strong reason
- use excessive shadows
- make filter controls oversized
- put large action buttons inside every table row
- show actions the user cannot access
- make every value a badge
- introduce different visual styles per module
- make simple tasks require many clicks

## Final Design Check

Before finishing a Celestia HRIS screen, verify:

```text
Is the page easy to scan?
Is the main action obvious?
Is there unnecessary information?
Are filters compact and useful?
Is the table readable?
Are permissions handled correctly?
Does the page match existing Celestia screens?
Can anything be simplified?
```

If the screen feels crowded, simplify it.

If a new component introduces a different visual style without a strong reason, reuse the existing Celestia pattern instead.

The entire HRIS should feel like one consistent product.
