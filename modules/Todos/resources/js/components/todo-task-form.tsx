import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'

export type TodoTaskFormValues = {
  title: string
  slug: string
  details: string
  status: string
  priority: string
  owner: string
  due_date: string
  is_blocked: boolean
}

type TaskEditingTarget = {
  id: number
  title: string
} | null

type ModuleMeta = {
  name: string
  description: string
}

type Option = {
  value: string
  label: string
}

type TodoTaskFormProps = {
  mode: 'create' | 'edit'
  module: ModuleMeta
  task: TaskEditingTarget
  initialValues: TodoTaskFormValues
  options: {
    statusOptions: Option[]
    priorityOptions: Option[]
  }
}

const slugify = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
const todosIndexUrl = '/todos'
const todosUpdateUrl = (id: number) => `/todos/${id}`

export default function TodoTaskForm({ mode, module, task, initialValues, options }: TodoTaskFormProps) {
  const form = useForm<TodoTaskFormValues>(initialValues)

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (task) {
      form.patch(todosUpdateUrl(task.id), {
        preserveScroll: true,
      })

      return
    }

    form.post(todosIndexUrl, {
      preserveScroll: true,
    })
  }

  const handleTitleChange = (value: string) => {
    const derivedSlug = slugify(form.data.title)

    form.setData('title', value)

    if (form.data.slug === '' || form.data.slug === derivedSlug) {
      form.setData('slug', slugify(value))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{mode === 'create' ? 'Create task' : `Edit ${task?.title}`}</CardTitle>
          <CardDescription>{module.description}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-6 lg:grid-cols-2">
          <div className="space-y-5 lg:col-span-2">
            <div className="grid gap-5 md:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-medium" htmlFor="title">Title</label>
                <Input id="title" value={form.data.title} onChange={(event) => handleTitleChange(event.target.value)} />
                <InputError message={form.errors.title} />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium" htmlFor="slug">Slug</label>
                <Input id="slug" value={form.data.slug} onChange={(event) => form.setData('slug', slugify(event.target.value))} />
                <InputError message={form.errors.slug} />
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium" htmlFor="details">Details</label>
              <Textarea id="details" value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} rows={8} />
              <InputError message={form.errors.details} />
            </div>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="status">Status</label>
            <NativeSelect id="status" className="w-full" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
              {options.statusOptions.map((option) => (
                <NativeSelectOption key={option.value} value={option.value}>{option.label}</NativeSelectOption>
              ))}
            </NativeSelect>
            <InputError message={form.errors.status} />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="priority">Priority</label>
            <NativeSelect id="priority" className="w-full" value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)}>
              {options.priorityOptions.map((option) => (
                <NativeSelectOption key={option.value} value={option.value}>{option.label}</NativeSelectOption>
              ))}
            </NativeSelect>
            <InputError message={form.errors.priority} />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="owner">Owner</label>
            <Input id="owner" value={form.data.owner} onChange={(event) => form.setData('owner', event.target.value)} />
            <InputError message={form.errors.owner} />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="due_date">Due date</label>
            <Input id="due_date" type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} />
            <InputError message={form.errors.due_date} />
          </div>

          <div className="flex items-start gap-3 rounded-xl border p-4 lg:col-span-2">
            <Checkbox id="is_blocked" checked={form.data.is_blocked} onCheckedChange={(checked) => form.setData('is_blocked', checked === true)} />
            <div className="space-y-1">
              <label className="text-sm font-medium" htmlFor="is_blocked">Task is blocked</label>
              <p className="text-sm text-muted-foreground">Blocked tasks make it easy to prototype queueing, escalations, or dependency views later.</p>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3 lg:col-span-2">
            <Button asChild variant="outline" type="button">
              <Link href={todosIndexUrl}>Cancel</Link>
            </Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Saving...' : mode === 'create' ? 'Create task' : 'Save changes'}
            </Button>
          </div>
        </CardContent>
      </Card>
    </form>
  )
}
