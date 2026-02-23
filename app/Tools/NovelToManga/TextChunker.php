<?php
/**
 * DGLab PWA - Text Chunker
 * 
 * Handles intelligent text segmentation for AI token limits while
 * maintaining narrative continuity across chunk boundaries.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * TextChunker Class
 * 
 * Intelligently chunks novel text for AI processing.
 */
class TextChunker
{
    /**
     * @var int $defaultChunkSize Default chunk size in tokens (approximate)
     */
    private int $defaultChunkSize = 4000;
    
    /**
     * @var float $tokenToCharRatio Approximate tokens per character
     */
    private float $tokenToCharRatio = 0.25;
    
    /**
     * @var int $overlapSize Overlap between chunks for continuity
     */
    private int $overlapSize = 200;

    /**
     * Chunk novel content for AI processing
     * 
     * @param array $chapters Array of chapter data
     * @param int|null $maxTokens Maximum tokens per chunk
     * @return array Chunked content with context
     */
    public function chunk(array $chapters, ?int $maxTokens = null): array
    {
        $maxTokens = $maxTokens ?? $this->defaultChunkSize;
        $maxChars = (int) ($maxTokens / $this->tokenToCharRatio);
        
        $chunks = [];
        $currentChunk = '';
        $currentContext = [];
        $chapterIndex = 0;
        
        foreach ($chapters as $chapter) {
            $chapterIndex++;
            $content = $chapter['content'] ?? '';
            $title = $chapter['title'] ?? "Chapter {$chapterIndex}";
            
            // Split content into paragraphs
            $paragraphs = $this->splitIntoParagraphs($content);
            
            foreach ($paragraphs as $paragraph) {
                $paragraphLength = strlen($paragraph);
                
                // Check if adding this paragraph would exceed limit
                if (strlen($currentChunk) + $paragraphLength > $maxChars && !empty($currentChunk)) {
                    // Save current chunk
                    $chunks[] = [
                        'content'   => trim($currentChunk),
                        'context'   => $this->buildContext($currentContext),
                        'chapter'   => $title,
                        'is_start'  => count($chunks) === 0,
                        'is_end'    => false,
                    ];
                    
                    // Start new chunk with overlap
                    $overlap = $this->extractOverlap($currentChunk);
                    $currentChunk = $overlap . "\n\n" . $paragraph;
                    
                    // Update context
                    $currentContext = $this->updateContext($currentContext, $currentChunk);
                } else {
                    // Add to current chunk
                    $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
                }
            }
            
            // Add chapter break marker
            $currentChunk .= "\n\n[CHAPTER_BREAK:{$title}]\n\n";
        }
        
        // Don't forget the last chunk
        if (!empty($currentChunk)) {
            $chunks[] = [
                'content'   => trim($currentChunk),
                'context'   => $this->buildContext($currentContext),
                'chapter'   => $title ?? 'Final',
                'is_start'  => count($chunks) === 0,
                'is_end'    => true,
            ];
        }
        
        // Mark the last chunk as end
        if (!empty($chunks)) {
            $chunks[count($chunks) - 1]['is_end'] = true;
        }
        
        return $this->addContinuityMarkers($chunks);
    }

    /**
     * Split content into paragraphs
     * 
     * @param string $content Text content
     * @return array Paragraphs
     */
    private function splitIntoParagraphs(string $content): array
    {
        // Split by double newlines or multiple spaces
        $paragraphs = preg_split('/\n\s*\n|\r\n\s*\r\n/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        // Clean up each paragraph
        $paragraphs = array_map(function ($p) {
            return trim(preg_replace('/\s+/', ' ', $p));
        }, $paragraphs);
        
        // Remove empty paragraphs
        return array_filter($paragraphs, function ($p) {
            return strlen($p) > 10; // Minimum paragraph length
        });
    }

    /**
     * Extract overlap text from end of chunk
     * 
     * @param string $chunk Chunk content
     * @return string Overlap text
     */
    private function extractOverlap(string $chunk): string
    {
        // Get last sentences up to overlap size
        $sentences = $this->splitIntoSentences($chunk);
        $overlap = '';
        
        for ($i = count($sentences) - 1; $i >= 0; $i--) {
            $candidate = $sentences[$i] . ' ' . $overlap;
            if (strlen($candidate) > $this->overlapSize * 5) { // Approximate char count
                break;
            }
            $overlap = $candidate;
        }
        
        return trim($overlap);
    }

    /**
     * Split text into sentences
     * 
     * @param string $text Text to split
     * @return array Sentences
     */
    private function splitIntoSentences(string $text): array
    {
        // Split by sentence endings
        $pattern = '/(?<=[.!?])\s+(?=[A-Z"\'])/';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
        
        return array_map('trim', $sentences);
    }

    /**
     * Build context summary for chunk
     * 
     * @param array $context Context data
     * @return string Context summary
     */
    private function buildContext(array $context): string
    {
        $parts = [];
        
        if (!empty($context['characters'])) {
            $parts[] = 'Characters: ' . implode(', ', array_slice($context['characters'], 0, 5));
        }
        
        if (!empty($context['location'])) {
            $parts[] = 'Location: ' . $context['location'];
        }
        
        if (!empty($context['mood'])) {
            $parts[] = 'Mood: ' . $context['mood'];
        }
        
        return implode(' | ', $parts);
    }

    /**
     * Update context from chunk content
     * 
     * @param array $currentContext Current context
     * @param string $chunk Chunk content
     * @return array Updated context
     */
    private function updateContext(array $currentContext, string $chunk): array
    {
        // Extract character names (capitalized words that appear multiple times)
        preg_match_all('/\b[A-Z][a-z]+\s+[A-Z][a-z]+\b/', $chunk, $names);
        $fullNames = array_count_values($names[0]);
        arsort($fullNames);
        
        // Extract single names
        preg_match_all('/\b[A-Z][a-zA-Z]+\b/', $chunk, $singleNames);
        $singleNameCounts = array_count_values($singleNames[0]);
        
        // Filter common words
        $commonWords = ['The', 'A', 'An', 'This', 'That', 'There', 'They', 'Their', 'What', 'When', 'Where', 'Who', 'Why', 'How'];
        foreach ($commonWords as $word) {
            unset($singleNameCounts[$word]);
        }
        
        arsort($singleNameCounts);
        
        // Combine and get top characters
        $characters = array_slice(array_keys($fullNames), 0, 3);
        foreach (array_keys($singleNameCounts) as $name) {
            if (count($characters) >= 5) break;
            if (!in_array($name, $characters)) {
                $characters[] = $name;
            }
        }
        
        $currentContext['characters'] = $characters;
        
        // Detect mood (simple keyword matching)
        $moodKeywords = [
            'tense' => ['tension', 'nervous', 'anxious', 'danger', 'threat'],
            'romantic' => ['love', 'kiss', 'embrace', 'heart', 'tender'],
            'action' => ['fight', 'battle', 'run', 'chase', 'explosion'],
            'mysterious' => ['mystery', 'secret', 'unknown', 'shadow', 'dark'],
            'happy' => ['joy', 'laugh', 'smile', 'celebrate', 'happy'],
            'sad' => ['sad', 'cry', 'tears', 'grief', 'loss', 'death'],
        ];
        
        $chunkLower = strtolower($chunk);
        $moodScores = [];
        
        foreach ($moodKeywords as $mood => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($chunkLower, $keyword);
            }
            if ($score > 0) {
                $moodScores[$mood] = $score;
            }
        }
        
        if (!empty($moodScores)) {
            arsort($moodScores);
            $currentContext['mood'] = array_key_first($moodScores);
        }
        
        return $currentContext;
    }

    /**
     * Add continuity markers between chunks
     * 
     * @param array $chunks Chunks array
     * @return array Chunks with continuity markers
     */
    private function addContinuityMarkers(array $chunks): array
    {
        $previousEnding = '';
        
        foreach ($chunks as $index => &$chunk) {
            // Add previous ending as context
            if (!empty($previousEnding)) {
                $chunk['previous_ending'] = $previousEnding;
            }
            
            // Store this chunk's ending for next iteration
            $sentences = $this->splitIntoSentences($chunk['content']);
            $lastSentences = array_slice($sentences, -3);
            $previousEnding = implode(' ', $lastSentences);
            
            // Add chunk metadata
            $chunk['chunk_number'] = $index + 1;
            $chunk['total_chunks'] = count($chunks);
        }
        
        return $chunks;
    }

    /**
     * Estimate token count from text
     * 
     * @param string $text Text to estimate
     * @return int Estimated token count
     */
    public function estimateTokens(string $text): int
    {
        return (int) (strlen($text) * $this->tokenToCharRatio);
    }

    /**
     * Get optimal chunk size for content
     * 
     * @param string $content Content to analyze
     * @param int $maxTokens Maximum allowed tokens
     * @return int Recommended chunk size
     */
    public function getOptimalChunkSize(string $content, int $maxTokens = 4000): int
    {
        $totalTokens = $this->estimateTokens($content);
        
        // If content fits in one chunk, return full size
        if ($totalTokens <= $maxTokens) {
            return $maxTokens;
        }
        
        // Calculate optimal chunks
        $optimalChunks = ceil($totalTokens / $maxTokens);
        $optimalSize = (int) ($totalTokens / $optimalChunks);
        
        // Add buffer for overlap
        return (int) ($optimalSize * 0.9);
    }

    /**
     * Merge processed chunks back together
     * 
     * @param array $processedChunks Array of processed chunk results
     * @return array Merged content
     */
    public function merge(array $processedChunks): array
    {
        $merged = [];
        
        foreach ($processedChunks as $chunk) {
            if (is_array($chunk)) {
                $merged = array_merge($merged, $chunk);
            }
        }
        
        // Remove duplicates based on content hash
        $seen = [];
        $unique = [];
        
        foreach ($merged as $item) {
            $hash = md5(serialize($item));
            if (!in_array($hash, $seen)) {
                $seen[] = $hash;
                $unique[] = $item;
            }
        }
        
        return $unique;
    }
}
