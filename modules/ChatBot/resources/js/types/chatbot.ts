export interface ChatConversation {
    id: string;
    title: string;
    updatedAt: string | null;
    updatedAtHuman: string | null;
}

export interface ChatToolCall {
    id: string;
    name: string;
    arguments: string | Record<string, unknown>;
}

export interface ChatToolResult {
    id: string;
    name: string;
    result: string;
    successful: boolean;
    error: string | null;
}

export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant' | 'system';
    status: 'completed' | 'streaming' | 'stopped' | 'error' | 'approval_required';
    finishReason: string | null;
    content: string;
    createdAt: string | null;
    reasoning: string;
    toolActivity: ChatToolCall[];
    toolOrder: Array<{ tool_id: string; text_offset: number }>;
    maxStepsReached: boolean;
    stepsUsed: number;
    debugInfo: Record<string, unknown> | null;
    error: string | null;
}

export interface ChatSettings {
    chatTitle: string;
    placeholder: string;
    showThinking: boolean;
    provider: string;
    model: string;
    debugMode: boolean;
}

export interface ChatIndexPageProps {
    conversations: ChatConversation[];
    activeConversation: ChatConversation | null;
    messages: ChatMessage[];
    settings: ChatSettings;
}

// ─── Settings types ────────────────────────────────────────────────────────────

export interface ChatBotGeneralSettings {
    chatbot_system_prompt: string;
    chatbot_chat_title: string;
    chatbot_placeholder: string;
    chatbot_show_thinking: boolean;
    chatbot_max_tool_steps: number;
}

export interface ChatBotProviderSettings {
    chatbot_provider: string;
    chatbot_model: string;
    chatbot_api_key: string;
}

export interface ChatBotToolSettings {
    [key: string]: boolean;
}

export interface ChatBotSettingsInitialValues {
    general: ChatBotGeneralSettings;
    provider: ChatBotProviderSettings;
    tools: ChatBotToolSettings;
}

export interface ProviderOption {
    value: string;
    label: string;
}

export interface ProviderRegistry {
    providersUrl: string;
    modelsBaseUrl: string;
    manageModelsUrl: string | null;
    createModelUrl: string | null;
}

export interface ToolDefinition {
    key: string;
    name: string;
    label: string;
    default: boolean;
    description: string;
    help: string;
}

export interface ToolGroup {
    title: string;
    tools: ToolDefinition[];
}

export interface ChatBotSettingsPageProps {
    section: string;
    initialValues: ChatBotSettingsInitialValues;
    providerOptions: ProviderOption[];
    providerRegistry: ProviderRegistry;
    toolGroups: ToolGroup[];
}
