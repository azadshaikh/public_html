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

## Rules

- Prefer `useAppForm()` over raw `useForm()` when the page needs client validation, draft persistence, or unsaved-changes protection.
- Pass a stable `rememberKey`. This form system is built around remembered state.
- Add `noValidate` on the `<form>` so browser validation tooltips do not bypass the shared UI.
- Use shadcn `Field*` primitives. Do not mix them with `InputError`.
- Put `data-invalid` on `Field` and `aria-invalid` on the actual control.
- Use `form.error('field')` and `form.invalid('field')` so client and server errors render the same way.
- Use `form.touch('field')` on blur and `form.setField('field', value)` on change.
- Submit with `form.submit(action, { setDefaultsOnSuccess: true })` when a successful save should clear dirty state.
- Exclude non-serializable draft values such as `File` inputs with `dontRemember`.

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
})

const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    form.submit(UserController.update(user.id), {
        preserveScroll: true,
        setDefaultsOnSuccess: true,
    })
}

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
</form>
```

## When raw Inertia `<Form>` is still fine

Use raw `<Form>` only for very simple forms that do not need:

- client-side validation
- dirty-form protection
- remembered draft state
- custom field-level state management

If any of those are needed, switch to `useAppForm()`.
