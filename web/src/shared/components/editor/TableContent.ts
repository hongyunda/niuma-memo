import { Node, mergeAttributes } from '@tiptap/core'

// 保留正文和剪贴板中的表格，供编辑和项目资源预览共用。
export const TableContent = Node.create({
  name: 'table', group: 'block', content: 'tableRow+', isolating: true,
  parseHTML: () => [{ tag: 'table' }],
  renderHTML: ({ HTMLAttributes }) => ['table', mergeAttributes(HTMLAttributes), ['tbody', 0]],
})
export const TableRow = Node.create({
  name: 'tableRow', content: '(tableCell | tableHeader)+',
  parseHTML: () => [{ tag: 'tr' }], renderHTML: () => ['tr', 0],
})
const spanAttributes = () => Object.fromEntries(['colspan', 'rowspan'].map(name => [name, {
  default: 1,
  parseHTML: (element: HTMLElement) => Math.max(1, Math.min(1000, Number(element.getAttribute(name)) || 1)),
  renderHTML: (attributes: Record<string, number>) => attributes[name]! > 1 ? { [name]: attributes[name] } : {},
}]))
export const TableCell = Node.create({
  name: 'tableCell', content: 'block+', isolating: true,
  addAttributes: spanAttributes,
  parseHTML: () => [{ tag: 'td' }], renderHTML: ({ HTMLAttributes }) => ['td', HTMLAttributes, 0],
})
export const TableHeader = Node.create({
  name: 'tableHeader', content: 'block+', isolating: true,
  addAttributes: spanAttributes,
  parseHTML: () => [{ tag: 'th' }], renderHTML: ({ HTMLAttributes }) => ['th', HTMLAttributes, 0],
})
