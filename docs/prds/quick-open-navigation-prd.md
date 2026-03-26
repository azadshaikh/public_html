# Quick Open Navigation PRD

## Summary

Add a global quick-open navigation dialog for authenticated app pages so users can press `Ctrl/Cmd + K`, search across the existing navigation tree, and jump directly to a page. The dialog should prioritize recently opened navigation links and keep keyboard interaction fast and predictable.

This is a shell-level productivity feature. It should reuse the application's existing shared navigation payload and current shadcn command/dialog primitives rather than introducing a parallel navigation source.

## PRD status

- Status: Complete
- Priority: Medium
- Owner: App shell / frontend UX
- Started: 2026-03-26
- Completed: 2026-03-26
- Current phase: Shipped v1

## Status tracker

| Track                                 | Status      | Notes                                                           |
| ------------------------------------- | ----------- | --------------------------------------------------------------- |
| PRD and implementation plan           | Complete    | Feature scope, constraints, and v1 behavior defined             |
| Navigation source audit               | Complete    | Shared Inertia navigation already provides the needed link tree |
| Global command palette implementation | Complete    | Dialog is mounted in the authenticated topbar and opens with `Ctrl/Cmd + K` |
| Recent-link tracking                  | Complete    | Recent successful page visits are stored client-side and surfaced in the dialog |
| Tests and regression guards           | Complete    | Focused Node tests, ESLint, and TypeScript validation passed    |

## Problem

The app has a growing navigation tree, but moving between pages still depends on scanning the sidebar or drilling through sections manually.

### Current issues

1. Navigation is browse-first, not search-first.
    - Users must visually scan the sidebar to find a page.
    - Deep or infrequently used pages are slower to reach.

2. There is no global keyboard-driven page switcher.
    - Users cannot jump to a known page without leaving the keyboard.

3. There is no recent-link affordance.
    - Common repeat visits are not surfaced proactively.

4. Navigation knowledge is duplicated in the UI.
    - The sidebar has the structure, but there is no second UI that reuses it as a searchable index.

## Product goal

Create a fast, global quick-open dialog for authenticated pages that:

- opens instantly from the keyboard,
- focuses the search input automatically,
- searches the existing navigation tree,
- surfaces recently opened navigation pages first,
- and opens the selected page with the correct navigation behavior.

## Principles

1. **Navigation stays single-source**
    - The quick opener must derive its entries from shared navigation, not from a hand-maintained frontend list.

2. **Keyboard-first**
    - Opening, searching, arrow navigation, and selection must work without reaching for the mouse.

3. **Simple v1**
    - Version 1 is for page navigation only.
    - It should not become a mixed command/action center yet.

4. **Recent data stays lightweight**
    - Store only recent URLs client-side and resolve them back to live navigation labels at render time.

5. **Respect existing navigation behavior**
    - External links and hard-reload links should still open correctly.
    - Non-GET navigation actions such as logout should not appear in the quick opener.

## Scope

### In scope

- Global authenticated quick-open dialog
- Keyboard shortcut: `Ctrl/Cmd + K`
- Search across shared navigation items
- Recent-link tracking in browser storage
- Topbar trigger with visible shortcut hint
- Focused frontend tests for navigation flattening and recent-link ordering

### Out of scope

- Global non-navigation actions such as logout, theme switching, or mutations
- Search across arbitrary URLs outside shared navigation
- Fuzzy search tuning beyond the default command palette behavior
- Backend API or shared-props changes
- Auth-page command palette support

## Users and use cases

### Primary users

- Admin users moving across many feature areas
- Power users who prefer keyboard navigation

### Key use cases

1. User presses `Ctrl/Cmd + K` from any authenticated page and jumps to a known page.
2. User opens the dialog with an empty query and selects a page from the recent list.
3. User searches for a nested page by child label, section label, or parent trail.

## Functional requirements

## 1. Entry source

- The dialog must use the shared Inertia `navigation` payload.
- It must flatten nested navigation items into searchable entries.
- It must include nested pages and preserve enough parent context to disambiguate similar labels.
- It must exclude non-page actions, especially navigation items with non-GET methods.

## 2. Keyboard shortcuts

- `Ctrl/Cmd + K` opens the dialog.
- Shortcut handling applies only on authenticated shell pages.
- When the dialog opens, the search input is focused automatically.

## 3. Search behavior

- Searching must match page label, section label, and parent trail.
- Results should preserve navigation order within their groups.
- The dialog should show a useful empty state when nothing matches.

## 4. Recent links

- Successful page visits should be recorded client-side as normalized URLs.
- Recent links should be capped to a small fixed list.
- Recent links should appear above the full navigation list when the query is empty.
- Recent display labels must come from the current navigation tree, not stale stored text.

## 5. Page opening behavior

- Internal app pages should open through Inertia navigation.
- Hard-reload links should use full-page navigation.
- `_blank` links should open in a new tab.
- Selecting the current page should close the dialog without redundant navigation.

## Non-functional requirements

- No backend changes for v1
- No new dependencies
- Minimal shell footprint
- Works on desktop and mobile authenticated layouts
- Accessible dialog semantics using existing dialog/command primitives

## UX notes

- The topbar should expose a visible trigger so the feature is discoverable.
- The trigger should show the primary shortcut hint on larger screens.
- Recent links should be hidden while an active search query is narrowing the full list.
- Each result should show a primary label plus a small context line with its section and parent trail.

## Proposed implementation

### Phase 1: Shared utility

- Add a small frontend utility that:
    - flattens navigation into quick-open entries,
    - normalizes URLs,
    - stores recent URLs,
    - and resolves recent URLs back to navigation entries.

### Phase 2: Global dialog

- Add a `QuickOpenDialog` component in the authenticated shell.
- Mount it from the topbar so it is available across app pages.
- Reuse the existing shadcn `CommandDialog` primitive.

### Phase 3: Navigation tracking

- Hook recent-link storage into successful Inertia GET navigations.
- Seed the current page into recent history on initial boot.

### Phase 4: Validation

- Add frontend tests for flattening, filtering eligibility, recent deduplication, and recent resolution.
- Run focused tests plus static validation for the touched frontend files.

## Success criteria

- Users can open the quick opener from any authenticated page.
- The search input is focused immediately on open.
- Recent links show meaningful entries after normal page navigation.
- Searching for common page names finds the expected navigation entries quickly.
- The dialog does not surface logout or other non-GET navigation actions.

## Completion summary

Version 1 shipped on 2026-03-26.

Delivered:

- Global quick-open dialog in the authenticated app shell
- `Ctrl/Cmd + K` keyboard shortcut
- Search across the shared navigation tree
- Recent-link prioritization based on successful page visits
- Filtering that excludes non-GET navigation actions such as logout
- Focused frontend validation for quick-open utility behavior plus lint and type checks

Validation completed:

- `node --test resources/js/lib/quick-open.test.js`
- `pnpm exec eslint resources/js/components/quick-open-dialog.tsx resources/js/lib/quick-open.js resources/js/lib/quick-open.test.js`
- `pnpm exec tsc --noEmit`
