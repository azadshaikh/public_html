import { Head, Link } from '@inertiajs/react'
import { BotIcon, LayoutDashboardIcon, MessageSquareTextIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import { dashboard } from '@/routes/index'
import type { BreadcrumbItem } from '@/types'

type ChatBotPageProps = {
  module: {
    name: string
    slug: string
    version: string
    description: string
    highlights: string[]
  }
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
  },
  {
    title: 'ChatBot plugin',
    href: '/chatbot',
  },
]

export default function ChatBotIndex({ module }: ChatBotPageProps) {
  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={`${module.name} plugin`}
      description={module.description}
      headerActions={
        <Button asChild>
          <Link href={dashboard()}>
            <LayoutDashboardIcon />
            Back to dashboard
          </Link>
        </Button>
      }
    >
      <Head title={`${module.name} plugin`} />

      <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <Card className="border-none bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-none ring-0">
          <CardHeader>
            <Badge variant="secondary" className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15">
              Conversation lab
            </Badge>
            <CardTitle className="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">
              A chatbot module mounted through the plugin runtime.
            </CardTitle>
            <CardDescription className="text-primary-foreground/75">
              Use this page to verify a second plugin can ship its own route, config, and Inertia page.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <div>
              <div className="text-sm text-primary-foreground/70">Slug</div>
              <div className="mt-1 text-xl font-semibold">{module.slug}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Version</div>
              <div className="mt-1 text-xl font-semibold">{module.version}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Plugin type</div>
              <div className="mt-1 text-xl font-semibold">Interactive</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Sample use case</CardTitle>
            <CardDescription>Standalone AI assistant tooling bundled as a plugin.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-xl border bg-muted/40 p-4">
              <div className="mb-2 flex items-center gap-2 font-medium">
                <BotIcon className="size-4 text-primary" />
                Bot workspace
              </div>
              <p className="text-sm text-muted-foreground">
                Add prompts, assistant settings, and chat sessions without polluting the core app directories.
              </p>
            </div>
            <div className="rounded-xl border bg-muted/40 p-4">
              <div className="mb-2 flex items-center gap-2 font-medium">
                <MessageSquareTextIcon className="size-4 text-primary" />
                Plugin-owned UI
              </div>
              <p className="text-sm text-muted-foreground">
                Everything on this page is served from the plugin while still inheriting the host shell.
              </p>
            </div>
          </CardContent>
        </Card>
      </section>

      <section className="mt-6 grid gap-4 md:grid-cols-3">
        {module.highlights.map((item) => (
          <Card key={item}>
            <CardHeader>
              <CardTitle className="text-lg">Plugin highlight</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">{item}</p>
            </CardContent>
          </Card>
        ))}
      </section>
    </AppLayout>
  )
}
