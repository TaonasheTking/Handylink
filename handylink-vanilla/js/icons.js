// ============================================================
// HANDYLINK — minimal inline icon set (Feather/lucide-style)
// Not pixel-identical to lucide-react, but same visual language:
// 24x24 viewbox, currentColor stroke, rounded caps/joins.
// Usage in HTML: <i data-icon="bell" data-size="17"></i>
// renderIcons() below swaps every <i data-icon> for real inline SVG.
// ============================================================

const ICONS = {
  menu: '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
  x: '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>',
  'chevron-right': '<polyline points="9 6 15 12 9 18"/>',
  'chevron-left': '<polyline points="15 6 9 12 15 18"/>',
  bell: '<path d="M6 10a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 20a2 2 0 0 0 4 0"/>',
  coins: '<circle cx="9" cy="9" r="6"/><path d="M14.5 8a6 6 0 1 1 0 10.5"/>',
  'wallet-cards': '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
  search: '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.2" y2="16.2"/>',
  'map-pin': '<path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.4"/>',
  plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  'arrow-right': '<line x1="4" y1="12" x2="20" y2="12"/><polyline points="14 6 20 12 14 18"/>',
  'arrow-left': '<line x1="20" y1="12" x2="4" y2="12"/><polyline points="10 6 4 12 10 18"/>',
  check: '<polyline points="5 12.5 10 17 19 7"/>',
  star: '<polygon points="12 2.5 15 9 22 9.8 17 14.4 18.3 21.3 12 17.9 5.7 21.3 7 14.4 2 9.8 9 9"/>',
  sparkles: '<path d="M12 3l1.7 4.6L18 9l-4.3 1.4L12 15l-1.7-4.6L6 9l4.3-1.4L12 3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/>',
  'sliders-horizontal': '<line x1="4" y1="7" x2="20" y2="7"/><circle cx="9" cy="7" r="2"/><line x1="4" y1="17" x2="20" y2="17"/><circle cx="16" cy="17" r="2"/>',
  'user-round': '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>',
  'briefcase-business': '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
  'hand-helping': '<path d="M11 13H6.5a2.5 2.5 0 0 1 0-5H10"/><path d="M14 8l4-2 3 2-6 5-4-1-4 3"/>',
  clock3: '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 13.5"/>',
  'shield-check': '<path d="M12 3l7 3v6c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V6l7-3z"/><polyline points="9 12 11.3 14.3 15 10"/>',
  'lock-keyhole': '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.3" r="1.3"/>',
  'credit-card': '<rect x="3" y="6" width="18" height="13" rx="2"/><line x1="3" y1="10.5" x2="21" y2="10.5"/>',
  'trending-up': '<polyline points="4 16 10 10 14 13 20 6"/><polyline points="14 6 20 6 20 12"/>',
  'image-plus': '<rect x="3" y="5" width="14" height="14" rx="2"/><circle cx="8" cy="10" r="1.5"/><path d="M5 17l4-4 3 3 3-4 3 3"/><line x1="20" y1="4" x2="20" y2="10"/><line x1="17" y1="7" x2="23" y2="7"/>',
  filter: '<polygon points="4 4 20 4 14 12.5 14 19 10 21 10 12.5 4 4"/>',
  'heart-handshake': '<path d="M12 6c-1.5-2-5-2.5-6.5-.5-1.8 2.3-.3 5 1.5 6.5l5 4.3 5-4.3c1.8-1.5 3.3-4.2 1.5-6.5-1.5-2-5-1.5-6.5.5z"/>',
  'circle-help': '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.7 1.2c0 1.6-2.2 1.8-2.2 3.3"/><line x1="12" y1="17" x2="12" y2="17"/>',
  grid: '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
  compass: '<circle cx="12" cy="12" r="9"/><polygon points="15 9 13 13 9 15 11 11"/>',
};

function renderIcons(root) {
  (root || document).querySelectorAll('i[data-icon]').forEach(function (el) {
    var name = el.getAttribute('data-icon');
    var size = el.getAttribute('data-size') || '18';
    var strokeWidth = el.getAttribute('data-stroke') || '2';
    var body = ICONS[name];
    if (!body) return;
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', size);
    svg.setAttribute('height', size);
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', strokeWidth);
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.classList.add('inline-block', 'align-middle', 'shrink-0');
    svg.innerHTML = body;
    el.replaceWith(svg);
  });
}
