<?php
namespace app\service;

use app\model\Attachment;
use app\model\Note;
use DOMDocument;
use DOMXPath;

class ProjectResourceService
{
    public function list(int $uid, int $projectId, string $category, string $keyword, int $page, int $limit): array
    {
        $items = [];
        if ($category !== 'link') {
            $q = Attachment::alias('a')->leftJoin('note n', 'n.id = a.note_id')
                ->where('a.user_id', $uid)->where('a.project_id', $projectId)
                ->where(fn($w) => $w->whereNull('a.note_id')->whereOr(fn($n) => $n->where('n.user_id', $uid)->whereNull('n.deleted_at')))
                ->field('a.*,n.content_text AS resource_content_text')->with(['note' => fn($n) => $n->field('id,title,project_id')]);
            if (in_array($category, ['image', 'video', 'pdf'], true)) $q->where('a.file_type', $category);
            elseif ($category === 'table') $q->where('a.file_type', 'excel');
            elseif ($category === 'other') $q->whereNotIn('a.file_type', ['image', 'video', 'pdf', 'excel']);
            foreach ($q->select()->toArray() as $a) {
                $content = (string) ($a['resource_content_text'] ?? '');
                unset($a['resource_content_text']);
                $item = [
                    'id' => 'file-' . $a['id'], 'kind' => 'file', 'title' => $a['original_name'],
                    'url' => $a['url'], 'note_id' => $a['note_id'], 'note_title' => $a['note']['title'] ?? '',
                    'updated_at' => $a['created_at'], 'attachment' => $a,
                ];
                if ($this->matches($item, $keyword, ($a['transcript'] ?? '') . ' ' . $content)) $items[] = $item;
            }
        }
        if ($category === 'link' || $category === 'table') {
            $notes = Note::where('user_id', $uid)->where('project_id', $projectId)->field('id,title,content,updated_at')->select();
            foreach ($notes as $note) {
                if (!$note->content) continue;
                $doc = new DOMDocument('1.0', 'UTF-8');
                $previous = libxml_use_internal_errors(true);
                $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $note->content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                if (!$loaded) continue;
                $xpath = new DOMXPath($doc);
                $nodes = $xpath->query($category === 'link' ? '//a[@href]' : '//table');
                $seen = [];
                foreach ($nodes as $i => $node) {
                    $item = [
                        'id' => $category . '-' . $note->id . '-' . $i,
                        'kind' => $category, 'note_id' => (int) $note->id,
                        'note_title' => $note->title, 'updated_at' => $note->updated_at,
                    ];
                    if ($category === 'link') {
                        $url = trim($node->getAttribute('href'));
                        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || isset($seen[$url])) continue;
                        $seen[$url] = true;
                        $item['url'] = $url;
                        $item['title'] = trim($node->textContent) ?: $url;
                    } else {
                        $item['title'] = $note->title . ' · 表格 ' . ($i + 1);
                        $item['html'] = NoteService::purify($doc->saveHTML($node));
                    }
                    if ($this->matches($item, $keyword, $node->textContent . ' ' . NoteService::toText($note->content))) $items[] = $item;
                }
            }
        }
        usort($items, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']) ?: strcmp($b['id'], $a['id']));
        $total = count($items);
        return [
            'list' => array_slice($items, ($page - 1) * $limit, $limit), 'total' => $total,
            'page' => $page, 'limit' => $limit, 'last_page' => max(1, (int) ceil($total / $limit)),
        ];
    }

    private function matches(array $item, string $keyword, string $extra): bool
    {
        return $keyword === '' || mb_stripos(implode(' ', [$item['title'], $item['note_title'], $item['url'] ?? '', $extra]), $keyword) !== false;
    }
}
