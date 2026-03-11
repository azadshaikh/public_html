import { Head, Link, usePage } from '@inertiajs/react'
import { LayoutDashboardIcon, PackageIcon, SparklesIcon } from 'lucide-react'
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
import type { BreadcrumbItem, SharedData } from '@/types'

type CmsPageProps = {
  module: {
    name: string
    slug: string
    version: string
    description: string
    features: string[]
    navigation: Array<{
      label: string
      href: string
    }>
  }
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
  },
  {
    title: 'CMS plugin',
    href: '/cms',
  },
]

export default function CmsIndex({ module }: CmsPageProps) {
  const { plugins } = usePage<SharedData>().props

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
          <CardHeader className="space-y-4">
            <Badge variant="secondary" className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15">
              Plugin runtime sample
            </Badge>
            <div className="space-y-2">
              <CardTitle className="text-3xl font-semibold tracking-tight md:text-4xl">
                {module.name} lives fully inside the plugin directory.
              </CardTitle>
              <CardDescription className="max-w-2xl text-primary-foreground/75">
                This page is rendered from the plugin, while reusing the host app layout, UI components,
                authentication state, and shared Inertia props.
              </CardDescription>
            </div>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <div>
              <div className="text-sm text-primary-foreground/70">Plugin slug</div>
              <div className="mt-1 text-xl font-semibold">{module.slug}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Version</div>
              <div className="mt-1 text-xl font-semibold">{module.version}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Shared registry</div>
              <div className="mt-1 text-xl font-semibold">{plugins.items.length} plugin(s)</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>What this proves</CardTitle>
            <CardDescription>
              The plugin runtime can register backend assets and frontend pages without moving code into the main app.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {module.features.map((feature) => (
              <div key={feature} className="flex items-start gap-3 rounded-xl border bg-muted/40 p-4">
                <SparklesIcon className="mt-0.5 size-4 text-primary" />
                <span className="text-sm text-muted-foreground">{feature}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </section>

      <section className="mt-6 grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Plugin navigation</CardTitle>
            <CardDescription>Navigation can be declared from plugin config and exposed through the module.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {module.navigation.map((item) => (
              <div key={item.href} className="flex items-center justify-between rounded-xl border p-4">
                <div>
                  <div className="font-medium">{item.label}</div>
                  <div className="text-sm text-muted-foreground">{item.href}</div>
                </div>
                <Button asChild variant="outline">
                  <Link href={item.href}>Open</Link>
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Enabled plugins</CardTitle>
            <CardDescription>Shared Inertia props can expose plugin metadata to any page.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {plugins.items.map((plugin) => (
              <div key={plugin.slug} className="flex items-center gap-3 rounded-xl border p-4">
                <div className="rounded-lg bg-primary/10 p-2 text-primary">
                  <PackageIcon className="size-4" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="font-medium">{plugin.name}</div>
                  <div className="text-sm text-muted-foreground">
                    {plugin.slug} • {plugin.url} • {plugin.inertiaNamespace}
                  </div>
                </div>
                <Badge variant="outline">v{plugin.version}</Badge>
              </div>
            ))}
          </CardContent>
        </Card>
      </section>
    </AppLayout>
  )
}
