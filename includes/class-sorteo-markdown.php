<?php
if (!defined('ABSPATH')) { exit; }

class Sorteo_SCO_Markdown {
    public static function to_html($markdown) {
        $md = str_replace(["\r\n", "\r"], "\n", (string)$markdown);

        // Extract fenced code blocks first
        $codeblocks = [];
        $md = preg_replace_callback('/```([a-zA-Z0-9_-]+)?\n([\s\S]*?)\n```/', function($m) use (&$codeblocks) {
            $lang = isset($m[1]) ? trim($m[1]) : '';
            $code = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $id = 'SCOCODE'.count($codeblocks).'X';
            $class = $lang ? ' class="language-'.esc_attr($lang).'"' : '';
            $codeblocks[$id] = '<pre><code'.$class.'>'.$code.'</code></pre>';
            return $id;
        }, $md);

        // Split into blocks by blank lines
        $blocks = preg_split('/\n\s*\n/', trim($md));
        $html_parts = [];

        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            // Table detection (simple): header | separator | rows
            if (count($lines) >= 2 && strpos($lines[0], '|') !== false && preg_match('/^\s*\|?\s*[:\-\s\|]+\s*\|?\s*$/', $lines[1])) {
                $html_parts[] = self::render_table($lines);
                continue;
            }
            // List detection
            if (preg_match('/^\s*([*\-+]\s+|\d+\.\s+)/', $lines[0])) {
                $html_parts[] = self::render_list($lines);
                continue;
            }
            // Blockquote detection
            if (preg_match('/^\s*>\s+/', $lines[0])) {
                $html_parts[] = self::render_blockquote($lines);
                continue;
            }
            // Header detection
            if (preg_match('/^\s*#{1,6}\s+/', $lines[0])) {
                foreach ($lines as $ln) {
                    $html_parts[] = self::render_heading($ln);
                }
                continue;
            }
            // Horizontal rule
            if (preg_match('/^\s*([-*_])\1{2,}\s*$/', trim($block))) {
                $html_parts[] = '<hr />';
                continue;
            }
            // Paragraph fallback (join lines)
            $p = self::render_inline(implode(' ', $lines));
            $html_parts[] = '<p>'.$p.'</p>';
        }

        $html = implode("\n", $html_parts);
        // Restore fenced code blocks
        foreach ($codeblocks as $k => $v) {
            $html = str_replace($k, $v, $html);
        }
        return $html;
    }

    private static function render_heading($line) {
        if (!preg_match('/^(\s*#{1,6})\s+(.*)$/', $line, $m)) {
            return '<p>'.self::render_inline($line).'</p>';
        }
        $level = min(6, max(1, strlen(trim($m[1]))));
        $text = self::render_inline(trim($m[2]));
        return '<h'.$level.'>'.$text.'</h'.$level.'>';
    }

    private static function render_list($lines) {
        // Determine ordered or unordered
        $isOrdered = preg_match('/^\s*\d+\.\s+/', $lines[0]);
        $tag = $isOrdered ? 'ol' : 'ul';
        $items = [];
        foreach ($lines as $ln) {
            if (preg_match('/^\s*(?:[*\-+]\s+|\d+\.\s+)(.+)$/', $ln, $m)) {
                $items[] = '<li>'.self::render_inline(trim($m[1])).'</li>';
            }
        }
        if (empty($items)) { return '<p>'.self::render_inline(implode(' ', $lines)).'</p>'; }
        return '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
    }

    private static function render_blockquote($lines) {
        $acc = [];
        foreach ($lines as $ln) {
            $acc[] = preg_replace('/^\s*>\s?/', '', $ln);
        }
        $inner = self::render_inline(implode(' ', $acc));
        return '<blockquote><p>'.$inner.'</p></blockquote>';
    }

    private static function render_table($lines) {
        // Expect header | sep | body rows
        $header = array_map('trim', explode('|', trim($lines[0], " |")));
        // $lines[1] is separator
        $rows = array_slice($lines, 2);
        $thead = '<thead><tr>'.implode('', array_map(function($h){ return '<th>'.self::render_inline($h).'</th>'; }, $header)).'</tr></thead>';
        $tbody_rows = [];
        foreach ($rows as $r) {
            if (trim($r) === '') continue;
            $cols = array_map('trim', explode('|', trim($r, " |")));
            if (count($cols) === 1 && $cols[0] === '') continue;
            $tds = [];
            foreach ($cols as $c) { $tds[] = '<td>'.self::render_inline($c).'</td>'; }
            $tbody_rows[] = '<tr>'.implode('', $tds).'</tr>';
        }
        $tbody = '<tbody>'.implode('', $tbody_rows).'</tbody>';
        return '<div class="sco-md-table"><table class="widefat striped">'.$thead.$tbody.'</table></div>';
    }

    private static function render_inline($text) {
        // Escape first
        $t = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // Inline code spans
        $t = preg_replace_callback('/`([^`]+)`/', function($m){
            return '<code>'.htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8').'</code>';
        }, $t);
        // Images ![alt](src)
        $t = preg_replace_callback('/!\[([^\]]*)\]\(([^\)\s]+)\)/', function($m){
            return '<img src="'.esc_url($m[2]).'" alt="'.esc_attr($m[1]).'" />';
        }, $t);
        // Links [text](url)
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^\)\s]+)\)/', function($m){
            $href = esc_url($m[2]);
            $txt = $m[1];
            return '<a href="'.$href.'" target="_blank" rel="noopener">'.self::render_inline_basic($txt).'</a>';
        }, $t);
        // Bold **text**
        $t = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t);
        // Italic *text*
        $t = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $t);
        return $t;
    }

    private static function render_inline_basic($text) {
        // For nested inline when building link text
        $t = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $t);
        return $t;
    }
}
