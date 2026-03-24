import { Link, router, usePage } from '@inertiajs/react';
import {
    CheckIcon,
    MessageSquarePlusIcon,
    PlusIcon,
    SendIcon,
    SquareIcon,
    Trash2Icon,
    WrenchIcon,
    XIcon,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    ChatConversation,
    ChatIndexPageProps,
    ChatMessage,
    ChatToolCall,
} from '../../types/chatbot';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'ChatBot', href: route('app.chatbot.index') },
];

/** Read the XSRF-TOKEN cookie that Laravel sets on every response. */
function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Extract the permission request ID embedded in an approval error string. */
function extractPermissionId(errorText: string | null): string | null {
    if (!errorText) {
        return null;
    }

    const match = errorText.match(/<tool_permission>([\s\S]*?)<\/tool_permission>/);

    if (!match) {
        return null;
    }

    try {
        const payload = JSON.parse(match[1]) as { id?: string };

        return payload.id ?? null;
    } catch {
        return null;
    }
}

interface PendingApproval {
    permissionId: string;
    messageId: string;
    toolId: string;
    toolName: string;
    convId: string;
    toolArgs: Record<string, unknown>;
}

interface StreamingState {
    messageId: string | null;
    content: string;
    reasoning: string;
    toolActivity: ChatToolCall[];
    status: 'streaming' | 'approval_required' | 'stopped' | 'error';
    error: string | null;
}

function ToolActivityBadge({ call }: { call: ChatToolCall }) {
    return (
        <span className="inline-flex items-center gap-1 rounded border bg-muted/50 px-1.5 py-0.5 text-xs text-muted-foreground">
            <WrenchIcon className="size-3" />
            {call.name}
        </span>
    );
}

function MessageBubble({
    message,
    showThinking,
}: {
    message: ChatMessage;
    showThinking: boolean;
}) {
    const isUser = message.role === 'user';
    const [showReasoning, setShowReasoning] = useState(false);

    return (
        <div className={`flex w-full gap-2 ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[80%] ${isUser ? 'items-end' : 'items-start'} flex flex-col gap-1`}>
                {/* Reasoning block */}
                {!isUser && showThinking && message.reasoning && (
                    <div className="w-full">
                        <button
                            type="button"
                            onClick={() => setShowReasoning((v) => !v)}
                            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <span className="italic">
                                {showReasoning ? '▾ Hide thinking' : '▸ Show thinking'}
                            </span>
                        </button>
                        {showReasoning && (
                            <div className="mt-1 rounded border border-dashed bg-muted/30 p-3 text-xs text-muted-foreground whitespace-pre-wrap">
                                {message.reasoning}
                            </div>
                        )}
                    </div>
                )}

                {/* Tool activity */}
                {!isUser && message.toolActivity.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {message.toolActivity.map((call) => (
                            <ToolActivityBadge key={call.id} call={call} />
                        ))}
                    </div>
                )}

                {/* Content bubble */}
                {message.content && (
                    <div
                        className={`rounded-2xl px-4 py-2.5 text-sm ${
                            isUser
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-foreground'
                        }`}
                    >
                        <div className="whitespace-pre-wrap break-words">{message.content}</div>
                    </div>
                )}

                {/* Error */}
                {message.error && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                        {message.error}
                    </div>
                )}

                {/* Max steps reached */}
                {message.maxStepsReached && (
                    <div className="rounded border border-warning/50 bg-warning/10 px-3 py-1.5 text-xs text-muted-foreground">
                        Max steps reached ({message.stepsUsed} steps used).
                    </div>
                )}

                {/* Timestamp */}
                {message.createdAt && (
                    <span className="text-xs text-muted-foreground/60">
                        {new Date(message.createdAt).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </span>
                )}
            </div>
        </div>
    );
}

function StreamingBubble({ state }: { state: StreamingState }) {
    return (
        <div className="flex w-full justify-start gap-2">
            <div className="flex max-w-[80%] flex-col items-start gap-1">
                {/* Tool activity during streaming */}
                {state.toolActivity.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {state.toolActivity.map((call) => (
                            <ToolActivityBadge key={call.id} call={call} />
                        ))}
                    </div>
                )}

                {state.content ? (
                    <div className="rounded-2xl bg-muted px-4 py-2.5 text-sm text-foreground">
                        <div className="whitespace-pre-wrap break-words">{state.content}</div>
                    </div>
                ) : (
                    <div className="flex items-center gap-1 rounded-2xl bg-muted px-4 py-3">
                        <span className="inline-block size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:0ms]" />
                        <span className="inline-block size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:150ms]" />
                        <span className="inline-block size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:300ms]" />
                    </div>
                )}
            </div>
        </div>
    );
}

function ApprovalCard({
    approval,
    onApprove,
    onDeny,
}: {
    approval: PendingApproval;
    onApprove: () => Promise<void>;
    onDeny: () => void;
}) {
    const [loading, setLoading] = useState<'approve' | 'deny' | null>(null);

    const handleApprove = async () => {
        setLoading('approve');

        try {
            await onApprove();
        } finally {
            setLoading(null);
        }
    };

    const handleDeny = () => {
        setLoading('deny');
        onDeny();
    };

    return (
        <div className="flex w-full justify-start">
            <div className="w-full max-w-md rounded-xl border bg-card p-4 shadow-sm">
                <div className="mb-3 flex items-center gap-2">
                    <WrenchIcon className="size-4 text-warning" />
                    <span className="text-sm font-medium">Tool approval required</span>
                </div>
                <p className="mb-1 text-xs text-muted-foreground">
                    The AI wants to run the{' '}
                    <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">
                        {approval.toolName}
                    </code>{' '}
                    tool.
                </p>
                {Object.keys(approval.toolArgs).length > 0 && (
                    <pre className="mb-3 max-h-40 overflow-auto rounded bg-muted px-2 py-1.5 text-xs">
                        {JSON.stringify(approval.toolArgs, null, 2)}
                    </pre>
                )}
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        variant="default"
                        onClick={handleApprove}
                        disabled={loading !== null}
                    >
                        <CheckIcon className="size-3.5" />
                        {loading === 'approve' ? 'Approving…' : 'Approve'}
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={handleDeny}
                        disabled={loading !== null}
                    >
                        <XIcon className="size-3.5" />
                        Deny
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function ChatIndex({
    conversations,
    activeConversation,
    messages,
    settings,
}: ChatIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canUseChatbot = Boolean(page.props.auth.abilities.useChatbot);

    const [localConversations, setLocalConversations] = useState<ChatConversation[]>(conversations);
    const [localActive, setLocalActive] = useState<ChatConversation | null>(activeConversation);
    const [localMessages, setLocalMessages] = useState<ChatMessage[]>(messages);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const [streamState, setStreamState] = useState<StreamingState | null>(null);
    const [pendingApproval, setPendingApproval] = useState<PendingApproval | null>(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const abortRef = useRef<AbortController | null>(null);

    // Sync from Inertia on navigation.
    useEffect(() => {
        setLocalMessages(messages);
        setLocalActive(activeConversation);
        setLocalConversations(conversations);
        setStreamState(null);
        setPendingApproval(null);
        setIsStreaming(false);
    }, [activeConversation, conversations, messages]);

    const scrollToBottom = useCallback(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [localMessages, scrollToBottom, streamState]);

    const buildUserMessage = (content: string): ChatMessage => ({
        id: `temp-${Date.now()}`,
        role: 'user',
        status: 'completed',
        finishReason: null,
        content,
        createdAt: new Date().toISOString(),
        reasoning: '',
        toolActivity: [],
        toolOrder: [],
        maxStepsReached: false,
        stepsUsed: 0,
        debugInfo: null,
        error: null,
    });

    const handleStream = useCallback(
        async (message: string, conversationId: string | null, isInternal = false, assistantMessageId: string | null = null) => {
            setIsStreaming(true);

            const initialStreamState: StreamingState = {
                messageId: null,
                content: '',
                reasoning: '',
                toolActivity: [],
                status: 'streaming',
                error: null,
            };

            setStreamState(initialStreamState);

            const abort = new AbortController();
            abortRef.current = abort;

            try {
                const response = await fetch(route('app.chatbot.stream'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/event-stream',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: JSON.stringify({
                        message: message || '__continue__',
                        conversation_id: conversationId ?? null,
                        ...(isInternal ? { internal: true } : {}),
                        ...(assistantMessageId ? { assistant_message_id: assistantMessageId } : {}),
                    }),
                    signal: abort.signal,
                });

                if (!response.ok || !response.body) {
                    throw new Error(`Stream failed: ${response.status}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let accContent = '';
                let accReasoning = '';
                let streamMsgId: string | null = null;
                let activeConvId = conversationId;

                while (true) {
                    const { done, value } = await reader.read();

                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) {
                            continue;
                        }

                        let payload: Record<string, unknown>;

                        try {
                            payload = JSON.parse(line.slice(6)) as Record<string, unknown>;
                        } catch {
                            continue;
                        }

                        const eventType = payload.type as string | undefined;

                        if (eventType === 'conversation_id') {
                            const newConvId = payload.conversation_id as string;
                            const newConvTitle = (payload.conversation_title as string) ?? 'New conversation';
                            activeConvId = newConvId;

                            if (!localActive) {
                                const newConv: ChatConversation = {
                                    id: newConvId,
                                    title: newConvTitle,
                                    updatedAt: null,
                                    updatedAtHuman: 'Just now',
                                };

                                setLocalActive(newConv);
                                setLocalConversations((prev) => [newConv, ...prev]);
                                router.replace(route('app.chatbot.show', newConvId), {
                                    preserveScroll: true,
                                    preserveState: true,
                                });
                            }
                        } else if (eventType === 'assistant_state') {
                            streamMsgId = (payload.message_id as string) ?? null;
                            const status = payload.status as string;

                            if (status === 'approval_required') {
                                setStreamState((prev) =>
                                    prev ? { ...prev, status: 'approval_required', messageId: streamMsgId } : null,
                                );
                            }
                        } else if (eventType === 'text_delta') {
                            accContent += (payload.delta as string) ?? '';
                            setStreamState((prev) =>
                                prev
                                    ? {
                                          ...prev,
                                          messageId: streamMsgId,
                                          content: accContent,
                                          reasoning: accReasoning,
                                      }
                                    : null,
                            );
                            scrollToBottom();
                        } else if (eventType === 'reasoning_delta') {
                            accReasoning += (payload.delta as string) ?? '';
                            setStreamState((prev) =>
                                prev
                                    ? {
                                          ...prev,
                                          messageId: streamMsgId,
                                          reasoning: accReasoning,
                                      }
                                    : null,
                            );
                        } else if (eventType === 'tool_call') {
                            const tc: ChatToolCall = {
                                id: (payload.tool_id as string) ?? '',
                                name: (payload.tool_name as string) ?? '',
                                arguments: (payload.arguments as Record<string, unknown>) ?? {},
                            };

                            setStreamState((prev) =>
                                prev
                                    ? {
                                          ...prev,
                                          toolActivity: [...prev.toolActivity, tc],
                                      }
                                    : null,
                            );
                        } else if (eventType === 'tool_result') {
                            const successful = payload.successful as boolean;
                            const error = payload.error as string | null;

                            if (!successful) {
                                const permId = extractPermissionId(error);

                                if (permId && streamMsgId && activeConvId) {
                                    const args = (() => {
                                        try {
                                            const raw = payload.arguments;

                                            return typeof raw === 'string'
                                                ? (JSON.parse(raw) as Record<string, unknown>)
                                                : (raw as Record<string, unknown>) ?? {};
                                        } catch {
                                            return {};
                                        }
                                    })();

                                    setPendingApproval({
                                        permissionId: permId,
                                        messageId: streamMsgId,
                                        toolId: (payload.tool_id as string) ?? '',
                                        toolName: (payload.tool_name as string) ?? '',
                                        convId: activeConvId,
                                        toolArgs: args,
                                    });
                                }
                            }
                        }
                    }
                }

                // Stream complete — reload page props to get canonical server state.
                router.reload({
                    only: ['messages', 'conversations', 'activeConversation'],
                    preserveScroll: true,
                    onSuccess: () => {
                        setStreamState(null);
                    },
                });
            } catch (err) {
                if ((err as { name?: string }).name === 'AbortError') {
                    setStreamState((prev) => (prev ? { ...prev, status: 'stopped' } : null));

                    return;
                }

                setStreamState((prev) =>
                    prev
                        ? {
                              ...prev,
                              status: 'error',
                              error: (err as Error).message ?? 'Streaming failed.',
                          }
                        : null,
                );
            } finally {
                setIsStreaming(false);
                abortRef.current = null;
            }
        },
        [localActive, scrollToBottom],
    );

    const handleSend = async () => {
        const text = input.trim();

        if (!text || isStreaming) {
            return;
        }

        setInput('');
        setPendingApproval(null);

        // Optimistic user message.
        setLocalMessages((prev) => [...prev, buildUserMessage(text)]);

        await handleStream(text, localActive?.id ?? null);
    };

    const handleStop = async () => {
        if (!streamState?.messageId || !localActive) {
            abortRef.current?.abort();

            return;
        }

        try {
            await fetch(
                route('app.chatbot.messages.stop', {
                    conversationId: localActive.id,
                    messageId: streamState.messageId,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: JSON.stringify({
                        content: streamState.content,
                        reasoning: streamState.reasoning,
                        tool_activity: streamState.toolActivity,
                        tool_order: [],
                        max_steps_reached: false,
                        steps_used: streamState.toolActivity.length,
                    }),
                },
            );
        } catch {
            // ignore
        }

        abortRef.current?.abort();
        setIsStreaming(false);
        setStreamState(null);
        router.reload({
            only: ['messages', 'conversations', 'activeConversation'],
            preserveScroll: true,
        });
    };

    const handleApprove = async () => {
        if (!pendingApproval) {
            return;
        }

        const { permissionId, messageId, toolId, convId } = pendingApproval;

        await fetch(
            route('app.chatbot.permissions.approve', {
                conversationId: convId,
                permissionId,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    assistant_message_id: messageId,
                    tool_id: toolId,
                }),
            },
        );

        setPendingApproval(null);
        await handleStream('__continue__', convId, true, messageId);
    };

    const handleDeny = () => {
        if (!pendingApproval) {
            return;
        }

        const { permissionId, messageId, toolId, convId } = pendingApproval;

        void fetch(
            route('app.chatbot.permissions.deny', {
                conversationId: convId,
                permissionId,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    assistant_message_id: messageId,
                    tool_id: toolId,
                }),
            },
        ).then(() => {
            setPendingApproval(null);
            router.reload({
                only: ['messages', 'conversations', 'activeConversation'],
                preserveScroll: true,
            });
        });
    };

    const handleDeleteConversation = async (conv: ChatConversation) => {
        const isActive = localActive?.id === conv.id;

        setLocalConversations((prev) => prev.filter((c) => c.id !== conv.id));

        if (isActive) {
            setLocalActive(null);
            setLocalMessages([]);
        }

        await fetch(
            route('app.chatbot.destroy', { conversationId: conv.id }),
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            },
        );
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void handleSend();
        }
    };

    if (!canUseChatbot) {
        return (
            <AppLayout breadcrumbs={breadcrumbs} title={settings.chatTitle}>
                <div className="flex h-48 items-center justify-center text-muted-foreground">
                    You do not have permission to use the ChatBot.
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={settings.chatTitle}
            contentClassName="p-0"
        >
            <div className="flex h-[calc(100vh-3.5rem)] overflow-hidden">
                {/* ── Sidebar ─────────────────────────────────────────────── */}
                <aside className="flex w-64 shrink-0 flex-col border-r bg-sidebar">
                    <div className="flex items-center justify-between px-3 py-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Conversations
                        </span>
                        <Link
                            href={route('app.chatbot.new')}
                            className="flex size-6 items-center justify-center rounded hover:bg-accent"
                            title="New conversation"
                        >
                            <PlusIcon className="size-3.5" />
                        </Link>
                    </div>

                    <nav className="flex-1 overflow-y-auto px-2 pb-2">
                        {localConversations.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-8 text-center text-xs text-muted-foreground">
                                <MessageSquarePlusIcon className="size-6 opacity-40" />
                                <span>No conversations yet</span>
                            </div>
                        ) : (
                            localConversations.map((conv) => (
                                <div
                                    key={conv.id}
                                    className={`group flex items-center rounded-lg px-2 py-1.5 ${
                                        localActive?.id === conv.id
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-foreground hover:bg-accent/50'
                                    }`}
                                >
                                    <Link
                                        href={route('app.chatbot.show', conv.id)}
                                        className="min-w-0 flex-1 truncate text-sm"
                                    >
                                        {conv.title || 'Untitled'}
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => void handleDeleteConversation(conv)}
                                        className="ml-1 hidden size-5 shrink-0 items-center justify-center rounded text-muted-foreground opacity-60 hover:bg-destructive/15 hover:text-destructive hover:opacity-100 group-hover:flex"
                                        title="Delete"
                                    >
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </div>
                            ))
                        )}
                    </nav>
                </aside>

                {/* ── Main area ────────────────────────────────────────────── */}
                <div className="flex flex-1 flex-col overflow-hidden">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 className="truncate text-sm font-medium text-foreground">
                            {localActive?.title ?? 'New conversation'}
                        </h2>
                    </div>

                    {/* Message list */}
                    <div className="flex-1 overflow-y-auto px-4 py-4">
                        {localMessages.length === 0 && !streamState ? (
                            <div className="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                <MessageSquarePlusIcon className="size-10 opacity-30" />
                                <p className="text-sm">{settings.placeholder}</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {localMessages.map((msg) => (
                                    <MessageBubble
                                        key={msg.id}
                                        message={msg}
                                        showThinking={settings.showThinking}
                                    />
                                ))}

                                {/* Streaming placeholder */}
                                {streamState && streamState.status !== 'approval_required' && (
                                    <StreamingBubble state={streamState} />
                                )}

                                {/* Tool approval card */}
                                {pendingApproval && (
                                    <ApprovalCard
                                        approval={pendingApproval}
                                        onApprove={handleApprove}
                                        onDeny={handleDeny}
                                    />
                                )}
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {/* Input area */}
                    <div className="border-t px-4 py-3">
                        <div className="flex items-end gap-2">
                            <Textarea
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder={settings.placeholder}
                                rows={2}
                                className="min-h-[2.5rem] flex-1 resize-none"
                                disabled={isStreaming}
                            />
                            {isStreaming ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon"
                                    onClick={() => void handleStop()}
                                    title="Stop"
                                >
                                    <SquareIcon className="size-4" />
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="icon"
                                    onClick={() => void handleSend()}
                                    disabled={!input.trim()}
                                    title="Send"
                                >
                                    <SendIcon className="size-4" />
                                </Button>
                            )}
                        </div>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            Press <kbd className="rounded border px-1 text-xs">Enter</kbd> to send,{' '}
                            <kbd className="rounded border px-1 text-xs">Shift+Enter</kbd> for newline
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
