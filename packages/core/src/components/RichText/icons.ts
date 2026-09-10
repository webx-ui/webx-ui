/**
 * Toolbar glyphs as raw path data, drawn on a 24×24 grid with a 1.7 stroke.
 * Inline rather than an icon package: it is a dozen paths, and a dependency for
 * that would be worse than the duplication.
 */
export const richTextIcons: Record<string, string> = {
  bold: 'M7 5h6a3.5 3.5 0 0 1 0 7H7zm0 7h7a3.5 3.5 0 0 1 0 7H7z',
  italic: 'M15 5h-5M14 19H9M13.5 5 10.5 19',
  strike:
    'M5 12h14M8 8.5A3 3 0 0 1 11 6h2a3 3 0 0 1 3 2.5M16 15.5A3 3 0 0 1 13 18h-2a3 3 0 0 1-3-2.5',
  code: 'm9 8-5 4 5 4M15 8l5 4-5 4',
  bulletList: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01',
  orderedList: 'M10 6h10M10 12h10M10 18h10M4 6h1v4M4 10h2M4 14h2v2H4v2h2',
  blockquote: 'M7 15V9h4v6H7l-2 3M15 15V9h4v6h-4l-2 3',
  hr: 'M4 12h16',
  link: 'M10 13a4 4 0 0 0 5.7.4l2.6-2.6a4 4 0 0 0-5.7-5.7l-1.5 1.5M14 11a4 4 0 0 0-5.7-.4l-2.6 2.6a4 4 0 0 0 5.7 5.7l1.5-1.5',
  table: 'M4 6h16v12H4zM4 10h16M10 10v8M4 14h16',
  image: 'M4 6h16v12H4zM4 15l4-4 4 4 3-3 5 5M9 9.5h.01',
  youtube:
    'M3 8.5A2.5 2.5 0 0 1 5.5 6h13A2.5 2.5 0 0 1 21 8.5v7a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 15.5zM10.5 9.5l4 2.5-4 2.5z',
  undo: 'M4 9h11a4.5 4.5 0 0 1 0 9h-4M4 9l4-4M4 9l4 4',
  redo: 'M20 9H9a4.5 4.5 0 0 0 0 9h4M20 9l-4-4M20 9l-4 4',
  rowAfter: 'M4 5h16M4 11h16M8 15v6M5 18h6',
  rowBefore: 'M4 19h16M4 13h16M8 3v6M5 6h6',
  columnAfter: 'M5 4v16M11 4v16M15 8h6M18 5v6',
  columnBefore: 'M19 4v16M13 4v16M3 8h6M6 5v6',
  deleteRow: 'M4 8h16M4 16h16M9 12h6',
  deleteColumn: 'M8 4v16M16 4v16M12 9v6',
  deleteTable: 'M5 7h14M9 7V5h6v2M7 7l1 12h8l1-12M10 11v4M14 11v4',
  mergeCells: 'M4 6h16v12H4zM12 6v3M12 15v3M9 12h6M9 12l2-2M9 12l2 2',
  check: 'm5 13 4 4L19 7',
  close: 'M6 6l12 12M18 6 6 18',
}
