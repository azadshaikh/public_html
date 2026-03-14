---
name: inertia-form-system
description: Builds and updates application forms in this project using the shared Inertia + shadcn form system. Use when creating or refactoring React forms, adding client-side validation, wiring dirty-form protection, showing consistent field errors, or deciding between the shared form hook and raw Inertia forms.
---

# Inertia Form System

Use this skill for application forms that need consistent client + server validation, dirty-form protection, and shadcn field styling.

## Default stack

- `useAppForm()` from `resources/js/hooks/use-app-form.ts`
- `Field`, `FieldGroup`, `FieldLabel`, `FieldDescription`, `FieldError` from `resources/js/components/ui/field.tsx`
- `FormErrorSummary` from `resources/js/components/forms/form-error-summary.tsx`
- validators from `resources/js/lib/forms.ts`
- dirty-form guard from `resources/js/hooks/use-dirty-form-guard.ts`

## Toast / flash feedback stack

- `showAppToast()` from `resources/js/components/forms/form-success-toast.tsx` — Sonner-based toast with `success`, `error`, and `info` variants.
- `initFlashToasts()` from `resources/js/hooks/use-flash-toast.ts` — global Inertia `router.on('navigate')` listener that auto-shows toasts for server-side flash messages (`session()->flash('success', …)`, `->with('error', …)`, etc.).
- `suppressNextFlashToast()` from `resources/js/hooks/use-flash-toast.ts` — prevents the global flash listener from showing a duplicate toast when `useAppForm`'s `successToast` option already handles it. Called automatically inside `useAppForm.submit()`.
- Flash data is shared via `HandleInertiaRequests` middleware (`flash.success`, `flash.error`, `flash.info`, `flash.status`).
- The `Toaster` component and `initFlashToasts()` are wired in `resources/js/app.tsx`.

## Rules

- Prefer `useAppForm()` over raw `useForm()` when the page needs client validation, draft persistence, or unsaved-changes protection.
- Pass a stable `rememberKey`. This form system is built around remembered state.
- Add `noValidate` on the `<form>` so browser validation tooltips do not bypass the shared UI.
- Use shadcn `Field*` primitives. Do not mix them with `InputError`.
- Put `data-invalid` on `Field` and `aria-invalid` on the actual control.
- Use `form.error('field')` and `form.invalid('field')` so client and server errors render the same way.
- Use `form.touch('field')` on blur and `form.setField('field', value)` on change.
- Use `FormErrorSummary` intentionally. Keep it for larger forms or multi-field saves, but for compact forms with one or two fields prefer `minMessages={2}` or omit the summary entirely so the field-level error stays the focus.
- Submit with `form.submit(action, { setDefaultsOnSuccess: true })` when a successful save should clear dirty state.
- Prefer `successToast` on `form.submit(...)` for save confirmations instead of inline "Saved" text. When `successToast` is set, `useAppForm` automatically calls `suppressNextFlashToast()` before showing its own toast — the global flash listener is suppressed for that request to avoid duplicates.
- For standalone `router.post/put/delete` calls outside `useAppForm` that also show a custom client-side toast, call `suppressNextFlashToast()` before `showAppToast()` in the `onSuccess` callback.
- Exclude non-serializable draft values such as `File` inputs with `dontRemember`.
- Do NOT use `ResourceFeedbackAlerts` for new pages. Flash messages are now handled globally via Sonner toasts. Existing `ResourceFeedbackAlerts` usage will be removed as pages are updated.

## Pattern

```tsx
const form = useAppForm({
    defaults: {
        name: user.name,
        avatar: null,
    },
    rememberKey: 'users.edit',
    dontRemember: ['avatar'],
    dirtyGuard: { enabled: true },
    rules: {
        name: [formValidators.required('Name')],
    },
});

const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    form.submit('put', route('app.users.update', { user: user.id }), {
        preserveScroll: true,
        setDefaultsOnSuccess: true,
        successToast: {
            title: 'User updated',
            description: 'The user details were saved successfully.',
        },
    });
};

<form noValidate onSubmit={handleSubmit}>
    <FormErrorSummary errors={form.errors} />

    <Field data-invalid={form.invalid('name') || undefined}>
        <FieldLabel htmlFor="name">Name</FieldLabel>
        <Input
            id="name"
            value={form.data.name}
            onChange={(event) => form.setField('name', event.target.value)}
            onBlur={() => form.touch('name')}
            aria-invalid={form.invalid('name') || undefined}
        />
        <FieldError>{form.error('name')}</FieldError>
    </Field>
</form>;
```

## Standalone router calls with custom toasts

When using `router.post/put/delete` directly (not via `useAppForm`) and you want a custom client-side toast instead of the auto-flash toast:

```tsx
import { showAppToast } from '@/components/forms/form-success-toast';
import { suppressNextFlashToast } from '@/hooks/use-flash-toast';

router.post(someAction(), undefined, {
    preserveScroll: true,
    onSuccess: () => {
        suppressNextFlashToast();
        showAppToast({
            title: 'Action completed',
            description: 'The operation was successful.',
        });
    },
});
```

If you do NOT call `suppressNextFlashToast()`, the global listener will auto-show a toast from the backend's flash message — which is the default and preferred behavior for most datagrid row/bulk actions.

## Toast variants

```tsx
showAppToast({ variant: 'success', title: 'Saved' }); // green (default)
showAppToast({ variant: 'error', title: 'Failed' }); // red
showAppToast({ variant: 'info', title: 'Note' }); // blue
```

## Error summary guidance

For dense forms with several inputs:

```tsx
<FormErrorSummary errors={form.errors} />
```

For compact forms where a summary alert feels repetitive:

```tsx
<FormErrorSummary errors={form.errors} minMessages={2} />
```

If the form is extremely small and the field-level error is enough, skip the summary completely.

## When raw Inertia `<Form>` is still fine

Use raw `<Form>` only for very simple forms that do not need:

- client-side validation
- dirty-form protection
- remembered draft state
- custom field-level state management

If any of those are needed, switch to `useAppForm()`.
