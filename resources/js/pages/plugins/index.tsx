import { Head, Link, useForm } from '@inertiajs/react'
import { ArrowLeftIcon, PackageIcon, SparklesIcon } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import AppLayout from '@/layouts/app-layout'
import { dashboard } from '@/routes/index'
import type { BreadcrumbItem, ManagedPlugin } from '@/types'

type PluginManagementPageProps = {
  managedPlugins: ManagedPlugin[]
  status?: string
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
  },
  {
    title: 'Plugins',
    href: '/plugins',
  },
]

export default function PluginsIndex({ managedPlugins, status }: PluginManagementPageProps) {
  const form = useForm({
    plugins: Object.fromEntries(
      managedPlugins.map((plugin) => [plugin.name, plugin.status]),
    ) as Record<string, 'enabled' | 'disabled'>,
  })

  const enabledCount = Object.values(form.data.plugins).filter((value) => value === 'enabled').length

  function setPluginStatus(pluginName: string, enabled: boolean) {
    form.setData('plugins', {
      ...form.data.plugins,
      [pluginName]: enabled ? 'enabled' : 'disabled',
    })
  }

  function submit() {
    form.patch('/plugins', {
      preserveScroll: true,
    })
  }

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title="Plugins"
      description="Enable or disable local plugins stored in the root plugins manifest."
      headerActions={
        <div className="flex flex-wrap gap-3">
          <Button asChild variant="outline">
            <Link href={dashboard()}>
              <ArrowLeftIcon />
              Back to dashboard
            </Link>
          </Button>
          <Button onClick={submit} disabled={form.processing}>
            Save changes
          </Button>
        </div>
      }
    >
      <Head title="Plugins" />

      <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <Card className="border-none bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-none ring-0">
          <CardHeader className="space-y-4">
            <Badge variant="secondary" className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15">
              Runtime controls
            </Badge>
            <div className="space-y-2">
              <CardTitle className="text-3xl font-semibold tracking-tight md:text-4xl">
                Control which plugins boot with the app.
              </CardTitle>
              <CardDescription className="max-w-2xl text-primary-foreground/75">
                Changes are written to the root plugins manifest and applied on the next request cycle.
              </CardDescription>
            </div>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <div>
              <div className="text-sm text-primary-foreground/70">Discovered plugins</div>
              <div className="mt-1 text-xl font-semibold">{managedPlugins.length}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Enabled now</div>
              <div className="mt-1 text-xl font-semibold">{enabledCount}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Manifest file</div>
              <div className="mt-1 text-xl font-semibold">plugins.json</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>How it works</CardTitle>
            <CardDescription>Each plugin is keyed by name and stored as enabled or disabled.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {[
              'Keep plugin code isolated inside its own plugin directory.',
              'Use the root manifest to decide what boots with the app.',
              'Share host UI, routes, auth, and layouts across plugins.',
            ].map((item) => (
              <div key={item} className="flex items-start gap-3 rounded-xl border bg-muted/40 p-4">
                <SparklesIcon className="mt-0.5 size-4 text-primary" />
                <span className="text-sm text-muted-foreground">{item}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </section>

      {status ? (
        <Alert className="mt-6">
          <PackageIcon className="size-4" />
          <AlertTitle>Saved</AlertTitle>
          <AlertDescription>{status}</AlertDescription>
        </Alert>
      ) : null}

      <section className="mt-6 grid gap-4">
        {managedPlugins.map((plugin) => {
          const pluginStatus = form.data.plugins[plugin.name] ?? 'disabled'
          const enabled = pluginStatus === 'enabled'

          return (
            <Card key={plugin.slug}>
              <CardHeader className="gap-4 md:flex-row md:items-start md:justify-between">
                <div className="space-y-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <CardTitle>{plugin.name}</CardTitle>
                    <Badge variant={enabled ? 'default' : 'outline'}>
                      {pluginStatus}
                    </Badge>
                    <Badge variant="secondary">v{plugin.version}</Badge>
                  </div>
                  <CardDescription className="max-w-2xl">
                    {plugin.description || 'No description provided.'}
                  </CardDescription>
                  <div className="text-sm text-muted-foreground">
                    Slug: {plugin.slug} • URL: {plugin.url} • Inertia namespace: {plugin.inertiaNamespace}
                  </div>
                </div>

                <div className="flex items-center gap-3 rounded-xl border bg-muted/40 px-4 py-3">
                  <div className="text-right">
                    <Label htmlFor={`plugin-${plugin.slug}`} className="text-sm font-medium">
                      {enabled ? 'Enabled' : 'Disabled'}
                    </Label>
                    <div className="text-xs text-muted-foreground">
                      Toggle plugin boot status
                    </div>
                  </div>
                  <Switch
                    id={`plugin-${plugin.slug}`}
                    checked={enabled}
                    disabled={form.processing}
                    onCheckedChange={(checked) => setPluginStatus(plugin.name, checked)}
                  />
                </div>
              </CardHeader>
            </Card>
          )
        })}
      </section>
    </AppLayout>
  )
}
