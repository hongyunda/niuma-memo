<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { onClickOutside, useEventListener } from '@vueuse/core'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import Color from '@tiptap/extension-color'
import TextStyle from '@tiptap/extension-text-style'
import { TableContent, TableRow, TableCell, TableHeader } from './editor/TableContent'
import { feedback } from '@/shared/ui/feedback'
import type { Attachment } from '@/shared/api/types'
import { clipboardImages, pickFiles, uploadFile, textToHtml } from '@/shared/utils/upload'

const props = withDefaults(defineProps<{ modelValue: string; placeholder?: string; noteId?: number; projectId?: number | null; editable?: boolean }>(), {
  placeholder: '写点什么…  支持粘贴 / 拖入图片',
  editable: true,
})
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void; (e: 'uploaded', a: Attachment): void }>()

const uploading = ref(0)
const showColorPicker = ref(false)
const colorButton = ref<HTMLButtonElement>()
const colorPanel = ref<HTMLDivElement>()
const customColor = ref('#1f2329')
const textColors = [
  { label: '黑色', value: '#1f2329' },
  { label: '灰色', value: '#4e5969' },
  { label: '红色', value: '#dc2626' },
  { label: '橙色', value: '#c2410c' },
  { label: '金色', value: '#a16207' },
  { label: '绿色', value: '#15803d' },
  { label: '蓝色', value: '#1d4ed8' },
  { label: '紫色', value: '#7c3aed' },
]

const NoteImage = Image.extend({
  addAttributes() {
    return { ...this.parent?.(), 'data-id': { default: null } }
  },
})

async function uploadAndInsert(files: File[]) {
  const ed = editor.value
  if (!files.length || !ed || !props.editable) return
  const targetNoteId = props.noteId
  const targetProjectId = props.projectId
  let bookmark = ed.state.selection.getBookmark()
  const track = ({ transaction }: { transaction: import('@tiptap/pm/state').Transaction }) => {
    bookmark = bookmark.map(transaction.mapping)
  }
  ed.on('transaction', track)
  uploading.value += files.length
  try {
    for (const f of files) {
      try {
        const att = await uploadFile(f, { note_id: targetNoteId, project_id: targetProjectId || undefined })
        if (ed.isDestroyed || (targetNoteId && props.noteId !== targetNoteId)) continue
        if (att.file_type === 'image') {
          const selection = bookmark.resolve(ed.state.doc)
          ed.chain().insertContentAt({ from: selection.from, to: selection.to }, {
            type: 'image', attrs: { src: att.url, alt: att.original_name, 'data-id': att.id },
          }).run()
        }
        emit('uploaded', att)
      } catch (e) {
        feedback.error('上传失败：' + (e as Error).message)
    } finally {
      uploading.value--
    }
  }
  } finally {
    ed.off('transaction', track)
  }
}

const editor = useEditor({
  content: props.modelValue || '',
  editable: props.editable,
  extensions: [
    StarterKit.configure({ heading: { levels: [1, 2, 3] }, codeBlock: {} }),
    NoteImage.configure({ inline: false, allowBase64: false }),
    Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
    Placeholder.configure({ placeholder: props.placeholder }),
    TaskList,
    TaskItem.configure({ nested: true }),
    TextStyle,
    Color,
    TableContent, TableRow, TableCell, TableHeader,
  ],
  editorProps: {
    attributes: { class: 'tiptap rich-content', role: 'textbox', 'aria-label': '记录内容', 'aria-multiline': 'true' },
    handlePaste: (_view, event) => {
      const files = clipboardImages(event.clipboardData)
      if (props.editable && files.length) {
        event.preventDefault()
        const text = event.clipboardData?.getData('text/plain')
        if (text) editor.value?.commands.insertContent(textToHtml(text))
        uploadAndInsert(files)
        return true
      }
      return false
    },
    handleDrop: (_view, event) => {
      const files = Array.from(event.dataTransfer?.files || [])
      if (props.editable && files.length) {
        event.preventDefault()
        event.stopPropagation()
        uploadAndInsert(files)
        return true
      }
      return false
    },
  },
  onUpdate: ({ editor }) => emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML()),
})

watch(() => props.modelValue, (v) => {
  const ed = editor.value
  if (!ed) return
  const current = ed.isEmpty ? '' : ed.getHTML()
  if (v !== current) ed.commands.setContent(v || '', false)
})

watch(() => props.editable, (v) => editor.value?.setEditable(v))
watch([() => props.noteId, () => props.editable], () => { showColorPicker.value = false })

const currentColor = computed(() => (editor.value?.getAttributes('textStyle').color as string | undefined) || '')
const currentHexColor = computed(() => {
  const color = currentColor.value.toLowerCase()
  // 浏览器解析已保存的 HTML 后，会把十六进制颜色转成 rgb()。
  const rgb = color.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/)
  return rgb ? '#' + rgb.slice(1).map(value => Number(value).toString(16).padStart(2, '0')).join('') : color
})

watch(showColorPicker, (show) => {
  if (show) customColor.value = /^#[0-9a-f]{6}$/.test(currentHexColor.value) ? currentHexColor.value : '#1f2329'
})

function setTextColor(color: string | null) {
  if (!props.editable || !editor.value) return
  const chain = editor.value.chain().focus()
  if (color) chain.setColor(color).run()
  else chain.unsetColor().run()
  showColorPicker.value = false
}

onClickOutside(colorPanel, () => { showColorPicker.value = false }, { ignore: [colorButton] })
useEventListener(document, 'keydown', (event) => {
  if (event.key !== 'Escape' || !showColorPicker.value) return
  event.preventDefault()
  event.stopPropagation()
  showColorPicker.value = false
  colorButton.value?.focus()
}, { capture: true })

async function insertImage() {
  const files = await pickFiles({ accept: 'image/*', multiple: true })
  uploadAndInsert(files)
}

function setLink() {
  const prev = editor.value?.getAttributes('link').href as string | undefined
  const url = window.prompt('链接地址', prev || 'https://')
  if (url === null) return
  if (!url.trim() || url === 'https://') editor.value?.chain().focus().unsetLink().run()
  else editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run()
}

function insertText(text: string) {
  editor.value?.chain().focus('end').insertContent(textToHtml(text)).run()
}

defineExpose({
  insertText,
  focus: () => editor.value?.commands.focus('end'),
  getHTML: () => (editor.value?.isEmpty ? '' : editor.value?.getHTML() || ''),
})

onBeforeUnmount(() => editor.value?.destroy())

type Tool = { name: string; icon: string; title: string; run: () => void; attrs?: Record<string, unknown>; sep?: boolean }
const tools: Tool[] = [
  { name: 'bold', icon: 'i-tabler-bold', title: '加粗 ⌘B', run: () => editor.value?.chain().focus().toggleBold().run() },
  { name: 'italic', icon: 'i-tabler-italic', title: '斜体 ⌘I', run: () => editor.value?.chain().focus().toggleItalic().run() },
  { name: 'strike', icon: 'i-tabler-strikethrough', title: '删除线', run: () => editor.value?.chain().focus().toggleStrike().run(), sep: true },
  { name: 'heading', icon: 'i-tabler-h-2', title: '标题', run: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), attrs: { level: 2 } },
  { name: 'bulletList', icon: 'i-tabler-list', title: '无序列表', run: () => editor.value?.chain().focus().toggleBulletList().run() },
  { name: 'orderedList', icon: 'i-tabler-list-numbers', title: '有序列表', run: () => editor.value?.chain().focus().toggleOrderedList().run() },
  { name: 'taskList', icon: 'i-tabler-list-check', title: '任务清单', run: () => editor.value?.chain().focus().toggleTaskList().run(), sep: true },
  { name: 'blockquote', icon: 'i-tabler-quote', title: '引用', run: () => editor.value?.chain().focus().toggleBlockquote().run() },
  { name: 'codeBlock', icon: 'i-tabler-code', title: '代码块', run: () => editor.value?.chain().focus().toggleCodeBlock().run() },
  { name: 'link', icon: 'i-tabler-link', title: '链接', run: setLink },
]
</script>

<template>
  <div class="note-editor" :class="{ readonly: !editable }">
    <div v-if="editable" class="editor-tools">
      <div class="toolbar">
        <template v-for="t in tools" :key="t.name">
          <button type="button" class="tb" :class="{ active: editor?.isActive(t.name, t.attrs) }" :title="t.title" @mousedown.prevent @click="t.run">
            <i :class="t.icon" />
          </button>
          <button v-if="t.name === 'strike'" :ref="el => { colorButton = el as HTMLButtonElement | undefined }" type="button" class="tb tb-color" :class="{ active: showColorPicker }" title="字体颜色" aria-label="字体颜色" :aria-expanded="showColorPicker" @mousedown.prevent @click="showColorPicker = !showColorPicker">
            <i class="i-tabler-letter-a" />
            <span class="color-indicator" :style="{ backgroundColor: currentColor || 'var(--t-1)' }" />
          </button>
          <span v-if="t.sep" class="tb-sep" />
        </template>
        <button type="button" class="tb" title="插入图片" @mousedown.prevent @click="insertImage"><i class="i-tabler-photo" /></button>
        <span class="flex-1" />
        <button type="button" class="tb" title="撤销 ⌘Z" :disabled="!editor?.can().undo()" @mousedown.prevent @click="editor?.chain().focus().undo().run()"><i class="i-tabler-arrow-back-up" /></button>
        <button type="button" class="tb" title="重做 ⇧⌘Z" :disabled="!editor?.can().redo()" @mousedown.prevent @click="editor?.chain().focus().redo().run()"><i class="i-tabler-arrow-forward-up" /></button>
        <span v-if="uploading" class="spinner" title="图片上传中" />
      </div>
      <div v-if="showColorPicker" ref="colorPanel" class="color-panel" role="group" aria-label="选择字体颜色">
        <div class="color-panel-title">字体颜色</div>
        <div class="color-options">
          <button v-for="color in textColors" :key="color.value" type="button" class="color-choice" :class="{ selected: currentHexColor === color.value }" :title="color.label" :aria-label="color.label" :aria-pressed="currentHexColor === color.value" @mousedown.prevent @click="setTextColor(color.value)">
            <span class="color-swatch" :style="{ backgroundColor: color.value }"><i v-if="currentHexColor === color.value" class="i-tabler-check" /></span>
          </button>
        </div>
        <label class="color-custom">
          <span>自定义颜色</span>
          <input v-model="customColor" type="color" aria-label="自定义字体颜色" @change="setTextColor(customColor)" />
        </label>
        <button type="button" class="color-reset" @mousedown.prevent @click="setTextColor(null)"><i class="i-tabler-color-picker-off" />恢复默认颜色</button>
      </div>
    </div>
    <EditorContent :editor="editor" class="editor-content" />
  </div>
</template>

<style scoped>
.editor-tools { position: sticky; top: 0; background: var(--bg-card); z-index: 2; }
.toolbar {
  display: flex; align-items: center; gap: 2px; overflow-x: auto; padding: 4px 0 6px; margin-bottom: 4px;
  border-bottom: 1px solid #f2f3f5; scrollbar-width: none;
}
.toolbar::-webkit-scrollbar { display: none; }
.tb {
  flex: 0 0 auto; width: 32px; height: 30px; border: 0; background: transparent; border-radius: 7px;
  font-size: 17px; color: var(--t-2); cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
  transition: background var(--dur), color var(--dur);
}
.tb:hover { background: var(--bg-muted); color: var(--t-1); }
.tb.active { background: var(--bg-active); color: var(--c-primary); }
.tb:disabled { opacity: 0.3; cursor: default; }
.tb-sep { width: 1px; height: 16px; background: var(--b-1); margin: 0 4px; flex: 0 0 auto; }
.tb-color { position: relative; padding-bottom: 5px; }
.color-indicator { position: absolute; bottom: 4px; width: 17px; height: 3px; border-radius: 1px; }
.color-panel { position: absolute; top: 100%; left: 0; width: 224px; max-width: 100%; padding: 12px; border-radius: var(--r-md); background: var(--bg-card); color: var(--t-1); box-shadow: var(--sh-pop); }
.color-panel-title { margin-bottom: 8px; font-size: 13px; font-weight: 600; }
.color-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; }
.color-choice { display: grid; place-items: center; height: 44px; padding: 0; border: 0; border-radius: var(--r-sm); background: transparent; cursor: pointer; }
.color-choice:hover, .color-choice.selected { background: var(--bg-muted); }
.color-swatch { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 4px; color: #fff; font-size: 18px; }
.color-custom { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; margin-top: 8px; font-size: 13px; }
.color-custom input { width: 44px; height: 36px; padding: 3px; border: 1px solid var(--b-1); border-radius: var(--r-sm); background: var(--bg-card); cursor: pointer; }
.color-reset { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; min-height: 36px; margin-top: 6px; border: 0; border-radius: var(--r-sm); background: var(--bg-muted); color: var(--t-2); font-size: 13px; cursor: pointer; }
.color-reset:hover { background: var(--bg-active); color: var(--c-primary); }
.tb-color:focus-visible, .color-choice:focus-visible, .color-reset:focus-visible, .color-custom input:focus-visible { outline: 2px solid var(--c-primary); outline-offset: 2px; }
.spinner { width: 14px; height: 14px; border: 2px solid var(--b-1); border-top-color: var(--c-primary); border-radius: 50%; animation: spin 0.8s linear infinite; margin-left: 6px; }
@keyframes spin { to { transform: rotate(360deg); } }
.readonly :deep(.tiptap) { min-height: 0; }
</style>
