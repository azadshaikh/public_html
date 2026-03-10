import { Head } from '@inertiajs/react'
import {
  ArrowUpRightIcon,
  CalendarClockIcon,
  FileTextIcon,
  FolderKanbanIcon,
  MessageSquareMoreIcon,
  NewspaperIcon,
  PlusIcon,
  SparklesIcon,
  UsersIcon,
  WandSparklesIcon,
} from 'lucide-react'
import { AppSidebar } from "@/components/app-sidebar"
import { Badge } from "@/components/ui/badge"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

const stats = [
  {
    title: 'Published posts',
    value: '128',
    change: '+12% this month',
    icon: NewspaperIcon,
  },
  {
    title: 'Pending review',
    value: '18',
    change: '5 need approval today',
    icon: FolderKanbanIcon,
  },
  {
    title: 'Active members',
    value: '2,431',
    change: '+8.4% audience growth',
    icon: UsersIcon,
  },
  {
    title: 'Comments',
    value: '342',
    change: '24 unread conversations',
    icon: MessageSquareMoreIcon,
  },
] as const

const pipeline = [
  { label: 'Drafts', value: 42 },
  { label: 'In review', value: 68 },
  { label: 'Scheduled', value: 53 },
  { label: 'Published', value: 84 },
] as const

const recentContent = [
  {
    title: 'Spring campaign landing page',
    type: 'Page',
    status: 'Published',
    author: 'Nadia Rahman',
    updated: '12 min ago',
    views: '4.8k',
  },
  {
    title: 'Product launch email sequence',
    type: 'Campaign',
    status: 'Review',
    author: 'Arjun Patel',
    updated: '34 min ago',
    views: '1.2k',
  },
  {
    title: 'Knowledge base: API authentication',
    type: 'Article',
    status: 'Scheduled',
    author: 'Sarah Chen',
    updated: '1 hour ago',
    views: '860',
  },
  {
    title: 'Homepage hero experiment B',
    type: 'Experiment',
    status: 'Draft',
    author: 'Imran Hossain',
    updated: '3 hours ago',
    views: '—',
  },
] as const

const activities = [
  {
    title: 'Homepage redesign approved',
    meta: 'Design team • 8 minutes ago',
  },
  {
    title: 'Editorial calendar updated for April',
    meta: 'Content ops • 26 minutes ago',
  },
  {
    title: 'Media library storage reached 78%',
    meta: 'System alert • 1 hour ago',
  },
  {
    title: 'New author onboarding completed',
    meta: 'People ops • 2 hours ago',
  },
] as const

function statusVariant(status: string): 'default' | 'secondary' | 'outline' | 'ghost' {
  switch (status) {
    case 'Published':
      return 'default'
    case 'Review':
      return 'secondary'
    case 'Scheduled':
      return 'outline'
    default:
      return 'ghost'
  }
}

export default function Page() {
  return (
    <SidebarProvider>
      <Head title="Dashboard" />
      <AppSidebar />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex items-center gap-2 px-4">
            <SidebarTrigger className="-ml-1" />
            <Separator
              orientation="vertical"
              className="mr-2 data-vertical:h-4 data-vertical:self-auto"
            />
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem className="hidden md:block">
                  <BreadcrumbLink href="#">
                    CMS workspace
                  </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator className="hidden md:block" />
                <BreadcrumbItem>
                  <BreadcrumbPage>Dashboard</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          </div>
        </header>

        <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
          <section className="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
            <Card className="border-none bg-gradient-to-br from-foreground to-foreground/85 text-background shadow-none ring-0">
              <CardHeader>
                <Badge variant="secondary" className="w-fit bg-background/15 text-background hover:bg-background/15">
                  CMS overview
                </Badge>
                <CardTitle className="text-3xl font-semibold tracking-tight text-background md:text-4xl">
                  Content performance at a glance
                </CardTitle>
                <CardDescription className="max-w-2xl text-background/75">
                  Monitor publishing velocity, editorial pipeline, audience engagement, and team activity from one place.
                </CardDescription>
              </CardHeader>
              <CardContent className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div className="grid gap-3 sm:grid-cols-3">
                  <div>
                    <div className="text-2xl font-semibold">94%</div>
                    <div className="text-sm text-background/70">on-time publishing rate</div>
                  </div>
                  <div>
                    <div className="text-2xl font-semibold">31.4k</div>
                    <div className="text-sm text-background/70">monthly readers</div>
                  </div>
                  <div>
                    <div className="text-2xl font-semibold">12</div>
                    <div className="text-sm text-background/70">campaigns running</div>
                  </div>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button variant="secondary" className="bg-background text-foreground hover:bg-background/90">
                    <PlusIcon />
                    New article
                  </Button>
                  <Button variant="outline" className="border-background/20 bg-transparent text-background hover:bg-background/10 hover:text-background">
                    <WandSparklesIcon />
                    Open content studio
                  </Button>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Today’s focus</CardTitle>
                <CardDescription>What your team should prioritize next.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="rounded-xl border bg-muted/40 p-4">
                  <div className="mb-1 flex items-center gap-2 text-sm font-medium">
                    <CalendarClockIcon className="size-4 text-primary" />
                    6 posts scheduled for today
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Confirm hero assets and SEO metadata before the noon publishing window.
                  </p>
                </div>
                <div className="rounded-xl border bg-muted/40 p-4">
                  <div className="mb-1 flex items-center gap-2 text-sm font-medium">
                    <SparklesIcon className="size-4 text-primary" />
                    AI suggestions ready
                  </div>
                  <p className="text-sm text-muted-foreground">
                    14 content briefs have optimization suggestions for titles, excerpts, and CTAs.
                  </p>
                </div>
                <div className="rounded-xl border bg-muted/40 p-4">
                  <div className="mb-1 flex items-center gap-2 text-sm font-medium">
                    <ArrowUpRightIcon className="size-4 text-primary" />
                    Traffic spike detected
                  </div>
                  <p className="text-sm text-muted-foreground">
                    The API authentication article is trending. Consider featuring it on the homepage.
                  </p>
                </div>
              </CardContent>
            </Card>
          </section>

          <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {stats.map((stat) => {
              const Icon = stat.icon

              return (
                <Card key={stat.title}>
                  <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <CardDescription>{stat.title}</CardDescription>
                        <CardTitle className="mt-2 text-3xl">{stat.value}</CardTitle>
                      </div>
                      <div className="rounded-xl border bg-muted/50 p-2">
                        <Icon className="size-5 text-primary" />
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div className="text-sm text-muted-foreground">{stat.change}</div>
                  </CardContent>
                </Card>
              )
            })}
          </section>

          <section className="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <Card>
              <CardHeader>
                <CardTitle>Editorial pipeline</CardTitle>
                <CardDescription>Track how content is moving through your workflow.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-5">
                {pipeline.map((item) => (
                  <div key={item.label} className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium">{item.label}</span>
                      <span className="text-muted-foreground">{item.value}%</span>
                    </div>
                    <Progress value={item.value} className="h-2" />
                  </div>
                ))}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Quick actions</CardTitle>
                <CardDescription>Common actions for editors and administrators.</CardDescription>
              </CardHeader>
              <CardContent className="grid gap-3">
                <Button className="justify-start">
                  <PlusIcon />
                  Create new post
                </Button>
                <Button variant="outline" className="justify-start">
                  <FolderKanbanIcon />
                  Review submissions
                </Button>
                <Button variant="outline" className="justify-start">
                  <FileTextIcon />
                  Manage page templates
                </Button>
                <Button variant="outline" className="justify-start">
                  <UsersIcon />
                  Invite team member
                </Button>
              </CardContent>
            </Card>
          </section>

          <section className="grid gap-4 xl:grid-cols-[1.45fr_0.8fr]">
            <Card>
              <CardHeader>
                <CardTitle>Recent content</CardTitle>
                <CardDescription>Latest updates across posts, pages, campaigns, and experiments.</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Title</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Author</TableHead>
                      <TableHead>Updated</TableHead>
                      <TableHead className="text-right">Views</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {recentContent.map((item) => (
                      <TableRow key={item.title}>
                        <TableCell className="font-medium">{item.title}</TableCell>
                        <TableCell className="text-muted-foreground">{item.type}</TableCell>
                        <TableCell>
                          <Badge variant={statusVariant(item.status)}>{item.status}</Badge>
                        </TableCell>
                        <TableCell>{item.author}</TableCell>
                        <TableCell className="text-muted-foreground">{item.updated}</TableCell>
                        <TableCell className="text-right font-medium">{item.views}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Team activity</CardTitle>
                <CardDescription>Recent changes made in your workspace.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {activities.map((activity, index) => (
                  <div key={activity.title}>
                    <div className="rounded-xl border bg-muted/30 p-4">
                      <p className="font-medium">{activity.title}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{activity.meta}</p>
                    </div>
                    {index !== activities.length - 1 ? <Separator className="my-3" /> : null}
                  </div>
                ))}
              </CardContent>
            </Card>
          </section>
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
