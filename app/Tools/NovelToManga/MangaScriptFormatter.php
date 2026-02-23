<?php
/**
 * DGLab PWA - Manga Script Formatter
 * 
 * Formats AI-processed content into structured manga script output.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * MangaScriptFormatter Class
 * 
 * Formats manga script content for various output formats.
 */
class MangaScriptFormatter
{
    /**
     * @var array $dialogueStyles Available dialogue styles
     */
    private array $dialogueStyles = [
        'standard' => [
            'format'      => ':character: :line',
            'emotion_fmt' => '(:emotion)',
        ],
        'dramatic' => [
            'format'      => ':CHARACTER: ":line!"',
            'emotion_fmt' => '[::emotion::]',
        ],
        'minimal'  => [
            'format'      => ':character: :line',
            'emotion_fmt' => '',
        ],
    ];

    /**
     * Combine processed chunks into unified script
     * 
     * @param array $chunks Processed chunks from AI
     * @param array $config Configuration options
     * @return array Combined script structure
     */
    public function combine(array $chunks, array $config): array
    {
        $combined = [];
        $panelCounter = 0;
        $currentChapter = '';
        
        foreach ($chunks as $chunkIndex => $chunk) {
            // Parse chunk content
            $scenes = $this->parseScenes($chunk);
            
            foreach ($scenes as $scene) {
                // Check for chapter break
                if (!empty($scene['chapter']) && $scene['chapter'] !== $currentChapter) {
                    $currentChapter = $scene['chapter'];
                }
                
                $formattedScene = [
                    'title'       => $scene['title'] ?? 'Scene ' . ($chunkIndex + 1),
                    'chapter'     => $currentChapter,
                    'description' => $scene['description'] ?? '',
                    'panels'      => [],
                ];
                
                foreach ($scene['panels'] ?? [] as $panel) {
                    $panelCounter++;
                    
                    $formattedPanel = [
                        'number'      => $panelCounter,
                        'description' => $panel['description'] ?? '',
                        'dialogue'    => [],
                    ];
                    
                    foreach ($panel['dialogue'] ?? [] as $dialogue) {
                        $formattedPanel['dialogue'][] = [
                            'character' => $dialogue['character'] ?? 'Unknown',
                            'line'      => $this->formatDialogue($dialogue['line'] ?? '', $config),
                            'emotion'   => $dialogue['emotion'] ?? '',
                            'type'      => $dialogue['type'] ?? 'speech', // speech, thought, shout, whisper
                        ];
                    }
                    
                    $formattedScene['panels'][] = $formattedPanel;
                }
                
                $combined[] = $formattedScene;
            }
        }
        
        return $combined;
    }

    /**
     * Parse scenes from AI output
     * 
     * @param array|string $chunk AI processed chunk
     * @return array Parsed scenes
     */
    private function parseScenes($chunk): array
    {
        $content = is_array($chunk) ? ($chunk['content'] ?? '') : $chunk;
        
        // If content is already structured, return it
        if (is_array($chunk) && isset($chunk['scenes'])) {
            return $chunk['scenes'];
        }
        
        // Parse from text format
        $scenes = [];
        
        // Split by scene markers
        $sceneTexts = preg_split('/\[?SCENE[:\s]+/i', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($sceneTexts as $sceneText) {
            $scene = $this->parseSceneText($sceneText);
            if (!empty($scene['panels'])) {
                $scenes[] = $scene;
            }
        }
        
        // If no scenes found, create one from entire content
        if (empty($scenes)) {
            $scenes[] = $this->parseSceneText($content);
        }
        
        return $scenes;
    }

    /**
     * Parse individual scene text
     * 
     * @param string $text Scene text
     * @return array Parsed scene
     */
    private function parseSceneText(string $text): array
    {
        $scene = [
            'title'       => $this->extractSceneTitle($text),
            'description' => $this->extractSceneDescription($text),
            'panels'      => [],
        ];
        
        // Extract panels
        $panelTexts = preg_split('/\[?PANEL[:\s]+/i', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($panelTexts as $panelText) {
            $panel = $this->parsePanelText($panelText);
            if (!empty($panel['dialogue']) || !empty($panel['description'])) {
                $scene['panels'][] = $panel;
            }
        }
        
        return $scene;
    }

    /**
     * Extract scene title
     * 
     * @param string $text Scene text
     * @return string|null Title
     */
    private function extractSceneTitle(string $text): ?string
    {
        // Look for title patterns
        if (preg_match('/^([^\n]+)/', trim($text), $matches)) {
            $firstLine = trim($matches[1]);
            if (strlen($firstLine) < 100 && !preg_match('/^(Panel|Scene|\d+)/i', $firstLine)) {
                return $firstLine;
            }
        }
        
        return null;
    }

    /**
     * Extract scene description
     * 
     * @param string $text Scene text
     * @return string Description
     */
    private function extractSceneDescription(string $text): string
    {
        // Look for description markers
        if (preg_match('/(?:Description|Setting|Scene):\s*([^\n]+(?:\n(?![A-Z][a-z]+:|\[?PANEL).*)*)/i', $text, $matches)) {
            return trim($matches[1]);
        }
        
        // Get first paragraph if it looks like description
        $lines = explode("\n", $text);
        $firstParagraph = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Stop if we hit dialogue or panel marker
            if (preg_match('/^(Panel|\[?PANEL|[A-Z][a-z]+:)/i', $line)) {
                break;
            }
            
            $firstParagraph .= ' ' . $line;
        }
        
        return trim($firstParagraph);
    }

    /**
     * Parse panel text
     * 
     * @param string $text Panel text
     * @return array Parsed panel
     */
    private function parsePanelText(string $text): array
    {
        $panel = [
            'description' => '',
            'dialogue'    => [],
        ];
        
        // Extract panel number if present
        if (preg_match('/^(\d+)[:\.]?\s*/', $text, $matches)) {
            $panel['number'] = (int) $matches[1];
            $text = substr($text, strlen($matches[0]));
        }
        
        // Extract panel description
        $lines = explode("\n", $text);
        $description = '';
        $inDialogue = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if this is dialogue (Character: Line format)
            if (preg_match('/^([A-Z][a-zA-Z\s]+):\s*(.+)$/', $line, $matches)) {
                $inDialogue = true;
                $character = trim($matches[1]);
                $dialogueLine = $matches[2];
                
                // Extract emotion if present
                $emotion = '';
                if (preg_match('/\(([^)]+)\)$/', $dialogueLine, $emotionMatch)) {
                    $emotion = $emotionMatch[1];
                    $dialogueLine = trim(substr($dialogueLine, 0, -strlen($emotionMatch[0])));
                }
                
                $panel['dialogue'][] = [
                    'character' => $character,
                    'line'      => $dialogueLine,
                    'emotion'   => $emotion,
                    'type'      => $this->detectDialogueType($dialogueLine),
                ];
            } elseif (!$inDialogue) {
                // This is part of the description
                $description .= ' ' . $line;
            }
        }
        
        $panel['description'] = trim($description);
        
        return $panel;
    }

    /**
     * Detect dialogue type based on formatting
     * 
     * @param string $line Dialogue line
     * @return string Type (speech, thought, shout, whisper)
     */
    private function detectDialogueType(string $line): string
    {
        // Check for thought bubbles (italics, parentheses, or thought markers)
        if (preg_match('/^\*[^*]+\*$|^\([^)]+\)$|^thought:/i', $line)) {
            return 'thought';
        }
        
        // Check for shouting (ALL CAPS or exclamation)
        if (preg_match('/^[A-Z\s!]+$/', $line) || substr_count($line, '!') > 2) {
            return 'shout';
        }
        
        // Check for whisper (small text markers or whisper indicators)
        if (preg_match('/^\([^)]+\)|^whisper:/i', $line)) {
            return 'whisper';
        }
        
        return 'speech';
    }

    /**
     * Format dialogue line
     * 
     * @param string $line Dialogue line
     * @param array $config Configuration
     * @return string Formatted line
     */
    private function formatDialogue(string $line, array $config): string
    {
        $style = $config['dialogue_style'] ?? 'standard';
        
        // Apply style-specific formatting
        switch ($style) {
            case 'dramatic':
                // Add emphasis to dramatic lines
                if (substr($line, -1) !== '!' && substr($line, -1) !== '?') {
                    $line .= '!';
                }
                break;
                
            case 'minimal':
                // Remove extra punctuation
                $line = preg_replace('/[!?]+$/', '', $line);
                break;
        }
        
        return $line;
    }

    /**
     * Format script as HTML for EPUB output
     * 
     * @param array $script Script structure
     * @param array $config Configuration
     * @return array HTML sections
     */
    public function formatAsHtml(array $script, array $config): array
    {
        $htmlSections = [];
        
        foreach ($script as $scene) {
            $htmlSection = [
                'title'       => $scene['title'],
                'description' => $scene['description'],
                'panels'      => [],
            ];
            
            foreach ($scene['panels'] as $panel) {
                $htmlPanel = [
                    'number'      => $panel['number'],
                    'description' => $this->formatPanelDescription($panel['description']),
                    'dialogue'    => [],
                ];
                
                foreach ($panel['dialogue'] as $dialogue) {
                    $htmlPanel['dialogue'][] = [
                        'character' => htmlspecialchars($dialogue['character']),
                        'line'      => htmlspecialchars($dialogue['line']),
                        'emotion'   => htmlspecialchars($dialogue['emotion']),
                        'type'      => $dialogue['type'],
                    ];
                }
                
                $htmlSection['panels'][] = $htmlPanel;
            }
            
            $htmlSections[] = $htmlSection;
        }
        
        return $htmlSections;
    }

    /**
     * Format panel description
     * 
     * @param string $description Raw description
     * @return string Formatted description
     */
    private function formatPanelDescription(string $description): string
    {
        // Capitalize first letter of sentences
        $description = ucfirst($description);
        
        // Add period if missing
        if (substr($description, -1) !== '.' && substr($description, -1) !== '!') {
            $description .= '.';
        }
        
        return $description;
    }

    /**
     * Generate CSS for manga script styling
     * 
     * @return string CSS content
     */
    public function generateCss(): string
    {
        return <<<CSS
/* Manga Script Styles */
.manga-script {
    font-family: "Georgia", serif;
    line-height: 1.8;
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.script-section {
    margin-bottom: 3rem;
    page-break-inside: avoid;
}

.scene-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    border-bottom: 2px solid #666;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.scene-description {
    font-style: italic;
    color: #666;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f5f5f5;
    border-left: 4px solid #999;
}

.panel {
    margin-bottom: 2rem;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}

.panel-number {
    font-weight: bold;
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
}

.panel-description {
    margin-bottom: 1rem;
    color: #444;
}

.dialogue {
    margin: 0.75rem 0;
    padding: 0.75rem;
    background: #f9f9f9;
    border-radius: 4px;
}

.character {
    font-weight: bold;
    color: #2c5282;
    margin-right: 0.5rem;
}

.line {
    color: #333;
}

.emotion {
    font-style: italic;
    color: #666;
    font-size: 0.875rem;
    margin-left: 0.5rem;
}

/* Dialogue types */
.dialogue.type-thought {
    font-style: italic;
    background: #f0f4f8;
}

.dialogue.type-shout {
    font-weight: bold;
    background: #fff5f5;
}

.dialogue.type-whisper {
    font-size: 0.9rem;
    background: #f7fafc;
}

/* Page breaks for print */
@media print {
    .script-section {
        page-break-inside: avoid;
    }
    
    .panel {
        page-break-inside: avoid;
    }
}
CSS;
    }

    /**
     * Export script to different formats
     * 
     * @param array $script Script structure
     * @param string $format Export format
     * @param array $config Configuration
     * @return string Exported content
     */
    public function export(array $script, string $format, array $config): string
    {
        switch ($format) {
            case 'screenplay':
                return $this->exportScreenplay($script, $config);
                
            case 'storyboard':
                return $this->exportStoryboard($script, $config);
                
            case 'text':
                return $this->exportPlainText($script, $config);
                
            default:
                return $this->exportPlainText($script, $config);
        }
    }

    /**
     * Export as screenplay format
     * 
     * @param array $script Script structure
     * @param array $config Configuration
     * @return string Screenplay content
     */
    private function exportScreenplay(array $script, array $config): string
    {
        $output = "MANGA SCRIPT\n";
        $output .= "=" . str_repeat("=", 78) . "\n\n";
        
        foreach ($script as $scene) {
            $output .= "SCENE: " . ($scene['title'] ?? 'Untitled') . "\n";
            $output .= str_repeat("-", 80) . "\n";
            
            if (!empty($scene['description'])) {
                $output .= "SETTING: " . $scene['description'] . "\n\n";
            }
            
            foreach ($scene['panels'] as $panel) {
                $output .= "PANEL " . $panel['number'] . "\n";
                $output .= "VISUAL: " . ($panel['description'] ?? 'No description') . "\n";
                
                foreach ($panel['dialogue'] as $dialogue) {
                    $type = strtoupper($dialogue['type'] ?? 'SPEECH');
                    $output .= sprintf("  %s (%s): %s\n",
                        strtoupper($dialogue['character']),
                        $type,
                        $dialogue['line']
                    );
                }
                
                $output .= "\n";
            }
            
            $output .= str_repeat("-", 80) . "\n\n";
        }
        
        return $output;
    }

    /**
     * Export as storyboard format
     * 
     * @param array $script Script structure
     * @param array $config Configuration
     * @return string Storyboard content
     */
    private function exportStoryboard(array $script, array $config): string
    {
        $output = "STORYBOARD\n";
        $output .= "=" . str_repeat("=", 78) . "\n\n";
        
        foreach ($script as $scene) {
            $output .= "SCENE: " . ($scene['title'] ?? 'Untitled') . "\n";
            
            foreach ($scene['panels'] as $panel) {
                $output .= "\n┌" . str_repeat("─", 38) . "┐\n";
                $output .= "│ PANEL " . str_pad($panel['number'], 31) . "│\n";
                $output .= "├" . str_repeat("─", 38) . "┤\n";
                
                // Description
                $desc = wordwrap($panel['description'] ?? '', 36, "\n");
                $descLines = explode("\n", $desc);
                foreach ($descLines as $line) {
                    $output .= "│ " . str_pad($line, 36) . " │\n";
                }
                
                // Dialogue
                if (!empty($panel['dialogue'])) {
                    $output .= "├" . str_repeat("─", 38) . "┤\n";
                    foreach ($panel['dialogue'] as $dialogue) {
                        $line = $dialogue['character'] . ": " . $dialogue['line'];
                        $wrapped = wordwrap($line, 34, "\n");
                        $wrappedLines = explode("\n", $wrapped);
                        foreach ($wrappedLines as $wline) {
                            $output .= "│ " . str_pad($wline, 36) . " │\n";
                        }
                    }
                }
                
                $output .= "└" . str_repeat("─", 38) . "┘\n";
            }
            
            $output .= "\n" . str_repeat("=", 80) . "\n\n";
        }
        
        return $output;
    }

    /**
     * Export as plain text
     * 
     * @param array $script Script structure
     * @param array $config Configuration
     * @return string Plain text content
     */
    private function exportPlainText(array $script, array $config): string
    {
        $output = '';
        
        foreach ($script as $scene) {
            $output .= ($scene['title'] ?? 'Scene') . "\n";
            $output .= str_repeat("=", strlen($scene['title'] ?? 'Scene')) . "\n\n";
            
            if (!empty($scene['description'])) {
                $output .= $scene['description'] . "\n\n";
            }
            
            foreach ($scene['panels'] as $panel) {
                $output .= "Panel " . $panel['number'] . ": ";
                $output .= ($panel['description'] ?? '') . "\n";
                
                foreach ($panel['dialogue'] as $dialogue) {
                    $output .= "  " . $dialogue['character'] . ": ";
                    $output .= $dialogue['line'];
                    if (!empty($dialogue['emotion'])) {
                        $output .= " (" . $dialogue['emotion'] . ")";
                    }
                    $output .= "\n";
                }
                
                $output .= "\n";
            }
        }
        
        return $output;
    }
}
