import { Head } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { dashboard } from '@/routes/index'
import type { BreadcrumbItem } from '@/types'
import TodoTaskForm from '../../components/todo-task-form'
import type { TodoTaskFormValues } from '../../components/todo-task-form'

type Option = { value: string; label: string }

type TodosCreatePageProps = {
  module: {
    name: string
    slug: string
    version: string
    description: string
  }
  task: null
  initialValues: TodoTaskFormValues
  options: {
    statusOptions: Option[]
    priorityOptions: Option[]
  }
}

export default function TodosCreate({ module, task, initialValues, options }: TodosCreatePageProps) {
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: module.name, href: '/todos' },
    { title: 'Create task', href: '/todos/create' },
  ]

  return (
    <AppLayout breadcrumbs={breadcrumbs} title={`Create ${module.name} task`} description={module.description}>
      <Head title={`Create ${module.name} task`} />
      <TodoTaskForm mode="create" module={module} task={task} initialValues={initialValues} options={options} />
    </AppLayout>
  )
}
