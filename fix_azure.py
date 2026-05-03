import sys
path = 'app/Services/MangaScript/AI/Providers/AzureOpenAiProvider.php'
content = open(path).read()
if 'public function getId()' not in content:
    content = content.replace('class AzureOpenAiProvider extends AbstractLLMProvider',
        'class AzureOpenAiProvider extends AbstractLLMProvider\n{\n    public function getId(): string { return "azure_openai"; }\n    public function getName(): string { return "Azure OpenAI"; }\n    public function getModels(): array { return $this->availableModels; }\n    protected function getDefaultModel(): string { return "gpt-4o"; }\n    public function chat(string $model, array $messages, array $options = []): LLMResponse { return $this->sendWithHistory($messages, null, array_merge($options, ["model" => $model])); }\n    public function chatStream(string $model, array $messages, array $options = []): \Generator { throw new \RuntimeException("Not implemented"); }')
    # Remove old class opening brace
    content = content.replace('class AzureOpenAiProvider extends AbstractLLMProvider\n{', 'class AzureOpenAiProvider extends AbstractLLMProvider', 1)
    open(path, 'w').write(content)
