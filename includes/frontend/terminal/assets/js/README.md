# 📄 Fáze 3 - PDF Viewer & Touch Gestures

## ✅ Co je v balíčku

```
phase3-pdf-viewer/
├── touch-gestures.js    # Reusable touch gesture library
├── pdf-viewer.js        # PDF.js wrapper pro map viewer
├── map.php             # Refactored map template
└── README.md           # This file
```

---

## 📦 Instalace

### Krok 1: Nahrát JavaScript soubory

```
includes/frontend/terminal/assets/js/
├── touch-gestures.js    # ← Sem
└── pdf-viewer.js        # ← Sem
```

### Krok 2: Nahrát template

```
includes/frontend/terminal/steps/training/
└── map.php              # ← Nahradit existující
```

### Krok 3: Načíst JS v kontroleru

V `terminal.php` v metodě `enqueue_assets()`:

```php
public function enqueue_assets() {
    $js_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/';
    $version = '3.0.0';
    
    // ... CSS enqueue ...
    
    // JavaScript
    wp_enqueue_script('jquery');
    
    // Touch gestures (dependency for PDF viewer)
    wp_enqueue_script(
        'saw-touch-gestures',
        $js_dir . 'touch-gestures.js',
        array(),
        $version,
        true
    );
    
    // PDF viewer (depends on touch-gestures)
    wp_enqueue_script(
        'saw-pdf-viewer',
        $js_dir . 'pdf-viewer.js',
        array('saw-touch-gestures'),
        $version,
        true
    );
}
```

### Krok 4: Ověřit strukturu

```
includes/frontend/terminal/
├── assets/
│   ├── css/
│   │   ├── terminal-base.css
│   │   ├── terminal-layout.css
│   │   ├── terminal-components.css
│   │   └── terminal-training.css
│   └── js/
│       ├── touch-gestures.js        # ← Nové
│       └── pdf-viewer.js            # ← Nové
├── components/
│   ├── training-header.php
│   ├── training-checkbox.php
│   ├── training-button.php
│   └── training-content-viewer.php
└── steps/training/
    └── map.php                      # ← Aktualizované
```

---

## 📖 Dokumentace - Touch Gestures

### **SAWTouchGestures Class**

Reusable knihovna pro detekci touch gest.

**Features:**
- ✅ Swipe detection (left, right, up, down)
- ✅ Tap detection
- ✅ Long press detection
- ✅ Velocity calculation
- ✅ Prevent scroll during horizontal swipe
- ✅ Configurable thresholds
- ✅ Debug mode

**Použití:**

```javascript
const element = document.getElementById('pdf-canvas');

const gestures = new SAWTouchGestures(element, {
    // Callbacks
    onSwipeLeft: function(data) {
        console.log('Swipe left with velocity:', data.velocity);
        pdfViewer.nextPage();
    },
    onSwipeRight: function(data) {
        console.log('Swipe right');
        pdfViewer.previousPage();
    },
    onTap: function(data) {
        console.log('Tapped at:', data.x, data.y);
    },
    onLongPress: function(data) {
        console.log('Long press duration:', data.duration);
    },
    
    // Options
    swipeThreshold: 50,      // Min distance for swipe (px)
    tapThreshold: 10,        // Max distance for tap (px)
    longPressThreshold: 500, // Min time for long press (ms)
    velocityThreshold: 0.3,  // Min velocity for swipe
    preventScroll: true,     // Prevent scroll during horizontal swipe
    debug: false             // Enable debug logging
});

// Update options
gestures.updateOptions({
    swipeThreshold: 100
});

// Destroy
gestures.destroy();
```

**API:**

| Method | Description |
|--------|-------------|
| `new SAWTouchGestures(element, options)` | Constructor |
| `updateOptions(newOptions)` | Update configuration |
| `destroy()` | Remove event listeners |

**Events:**

| Callback | Data | Description |
|----------|------|-------------|
| `onSwipeLeft` | `{deltaX, velocity}` | Swipe left detected |
| `onSwipeRight` | `{deltaX, velocity}` | Swipe right detected |
| `onSwipeUp` | `{deltaY, velocity}` | Swipe up detected |
| `onSwipeDown` | `{deltaY, velocity}` | Swipe down detected |
| `onTap` | `{x, y}` | Quick tap detected |
| `onLongPress` | `{x, y, duration}` | Long press detected |

---

## 📖 Dokumentace - PDF Viewer

### **SAWPDFViewer Class**

PDF.js wrapper pro zobrazení map/dokumentů.

**Features:**
- ✅ PDF.js rendering do canvas
- ✅ Touch gestures (swipe left/right)
- ✅ Keyboard navigation (arrow keys)
- ✅ Button controls (prev/next)
- ✅ Page indicator (1/5)
- ✅ Progress tracking
- ✅ Responsive scaling
- ✅ Loading progress
- ✅ Completion callback
- ✅ Auto-load PDF.js from CDN

**Použití:**

```javascript
const viewer = new SAWPDFViewer({
    // Required
    pdfUrl: '/path/to/document.pdf',
    
    // Optional
    canvasId: 'pdf-canvas',          // Canvas element ID (default: 'pdf-canvas')
    debug: false,                     // Enable debug logging
    
    // Callbacks
    onComplete: function(data) {
        // Called when all pages have been viewed
        console.log('All pages viewed!', data);
        // data = { totalPages: 5, viewedPages: 5 }
        
        // Enable continue button
        document.getElementById('continue-btn').disabled = false;
    },
    
    onPageChange: function(data) {
        // Called on every page change
        console.log('Page changed:', data);
        // data = { currentPage: 2, totalPages: 5, viewedPages: 2 }
    }
});

// API methods
viewer.nextPage();          // Go to next page
viewer.previousPage();      // Go to previous page
viewer.goToPage(3);         // Go to specific page
viewer.destroy();           // Clean up resources
```

**Required HTML:**

```html
<!-- Canvas for rendering -->
<canvas id="pdf-canvas"></canvas>

<!-- Page indicator -->
<div id="pdf-page-indicator">1 / 5</div>

<!-- Navigation buttons -->
<button id="pdf-prev">Previous</button>
<button id="pdf-next">Next</button>

<!-- Optional: Progress text -->
<div id="pdf-progress-text">60%</div>

<!-- Optional: Loading progress -->
<div id="pdf-loading-progress"></div>
```

**Responsive Scaling:**

Viewer automaticky přizpůsobí scale podle šířky obrazovky:
- Mobile (<768px): scale = 1.0
- Tablet (768-1023px): scale = 1.5
- Desktop (1024px+): scale = 2.0

---

## 🎯 Kompletní příklad použití

### **HTML Struktura:**

```html
<div class="saw-training-fullscreen">
    <a href="/terminal/" class="saw-terminal-home-btn">🏠</a>
    
    <div class="saw-training-container">
        <!-- Header -->
        <div class="saw-training-header">
            <div class="saw-training-icon">🗺️</div>
            <h1 class="saw-training-title">Mapa objektu</h1>
            <p class="saw-training-subtitle">Projděte si mapu areálu</p>
        </div>
        
        <div class="saw-training-card">
            <!-- PDF Viewer -->
            <div class="saw-pdf-viewer-container">
                <canvas id="pdf-canvas"></canvas>
                
                <div class="saw-pdf-navigation">
                    <button id="pdf-prev">←</button>
                    <div id="pdf-page-indicator">1 / 5</div>
                    <button id="pdf-next">→</button>
                </div>
            </div>
            
            <!-- Checkbox -->
            <label class="saw-training-confirm-box">
                <input type="checkbox" id="map-confirmed" disabled>
                <span>Potvrzuji, že jsem si prohlédl mapu</span>
            </label>
            
            <!-- Button -->
            <button id="continue-btn" disabled>Pokračovat</button>
        </div>
    </div>
</div>
```

### **JavaScript Inicializace:**

```javascript
// Initialize PDF viewer
const viewer = new SAWPDFViewer({
    pdfUrl: '/wp-content/uploads/saw-training/map.pdf',
    canvasId: 'pdf-canvas',
    debug: true,
    
    onComplete: function(data) {
        console.log('All ' + data.totalPages + ' pages viewed!');
        
        // Enable checkbox
        const checkbox = document.getElementById('map-confirmed');
        checkbox.disabled = false;
    },
    
    onPageChange: function(data) {
        console.log('Now on page ' + data.currentPage + ' of ' + data.totalPages);
    }
});

// Enable button when checkbox checked
document.getElementById('map-confirmed').addEventListener('change', function() {
    document.getElementById('continue-btn').disabled = !this.checked;
});
```

---

## 🎨 Styling (už v CSS)

Všechny styly jsou již zahrnuty v **terminal-components.css**:

```css
.saw-pdf-viewer-container { ... }
#pdf-canvas { ... }
.saw-pdf-navigation { ... }
.saw-pdf-nav-btn { ... }
.saw-pdf-indicator { ... }
```

Není potřeba žádný další CSS!

---

## 🔧 Jak to funguje

### **1. PDF.js Loading**

```javascript
// Auto-load PDF.js from CDN if not present
if (typeof pdfjsLib === 'undefined') {
    await this.loadPDFJS();
}

// Set worker
pdfjsLib.GlobalWorkerOptions.workerSrc = 
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
```

### **2. Document Loading**

```javascript
const loadingTask = pdfjsLib.getDocument(pdfUrl);

// Progress tracking
loadingTask.onProgress = (progress) => {
    const percent = (progress.loaded / progress.total * 100).toFixed(0);
    updateProgress(percent);
};

const pdfDoc = await loadingTask.promise;
```

### **3. Page Rendering**

```javascript
// Get page
const page = await pdfDoc.getPage(pageNum);

// Calculate responsive scale
const viewport = page.getViewport({ scale: this.scale });
const containerWidth = container.clientWidth - 32;
const scale = containerWidth / viewport.width;

// Render to canvas
await page.render({
    canvasContext: ctx,
    viewport: scaledViewport
}).promise;
```

### **4. Touch Gestures**

```javascript
const gestures = new SAWTouchGestures(canvas, {
    onSwipeLeft: () => this.nextPage(),
    onSwipeRight: () => this.previousPage(),
    preventScroll: true
});
```

### **5. Progress Tracking**

```javascript
// Track viewed pages
this.viewedPages.add(pageNum);

// Check completion
if (this.viewedPages.size === this.totalPages) {
    this.onComplete({ totalPages, viewedPages });
}
```

---

## 📱 Touch Gestures na mobilu

**Jak to funguje:**

1. **Touch Start** → Zapamatuj pozici
2. **Touch Move** → Detekuj směr (horizontal vs vertical)
3. **Touch End** → Vyhodnoť:
   - Swipe left → Next page
   - Swipe right → Previous page
   - Tap → (zatím nic)

**Prevence scrollu:**

```javascript
// Pokud je horizontal swipe, zabraň vertikálnímu scrollu
if (moveX > moveY && this.options.preventScroll) {
    event.preventDefault();
}
```

---

## 🐛 Debugging

### **Console Logs:**

```javascript
// Touch Gestures
[SAW Touch Gestures] Initialized on element: <canvas>
[SAW Touch Gestures] Touch start: {x: 150, y: 300}
[SAW Touch Gestures] Horizontal swipe detected
[SAW Touch Gestures] Triggering: onSwipeLeft {deltaX: -75, velocity: 0.5}

// PDF Viewer
[SAW PDF Viewer] Initialized successfully
[SAW PDF Viewer] PDF loaded: {totalPages: 5, url: '/path/to/map.pdf'}
[SAW PDF Viewer] Rendered page: 2
[SAW PDF Viewer] All pages viewed - triggering completion
```

### **Enable Debug Mode:**

```javascript
const viewer = new SAWPDFViewer({
    pdfUrl: '/path/to/map.pdf',
    debug: true  // ← Enable debug logging
});

const gestures = new SAWTouchGestures(element, {
    debug: true  // ← Enable debug logging
});
```

---

## ✅ Checklist - Co je hotovo

- ✅ Touch gesture detection library
- ✅ PDF.js wrapper class
- ✅ Auto-load PDF.js from CDN
- ✅ Responsive canvas scaling
- ✅ Page navigation (buttons, keyboard, swipe)
- ✅ Progress tracking
- ✅ Completion callback
- ✅ Refactored map.php template
- ✅ Integration s PHP components

---

## 🚀 Next Steps - Fáze 4

**Co bude následovat:**
1. Refactor `video.php` - fullscreen mode
2. Enhanced progress tracking pro video
3. Unified layout pro video step

---

## 📊 Statistiky

**JavaScript soubory:**
- `touch-gestures.js`: 320 řádků (~8 KB)
- `pdf-viewer.js`: 420 řádků (~12 KB)
- **Celkem: 740 řádků, ~20 KB** (před minifikací)

**Dependencies:**
- PDF.js: 3.11.174 (auto-loaded from CDN)
- jQuery: není potřeba (vanilla JS)

**Browser Support:**
- Chrome/Edge: ✅
- Firefox: ✅
- Safari: ✅
- Mobile Safari: ✅
- Chrome Mobile: ✅

---

## 🤔 FAQ

**Q: Proč PDF.js místo `<embed>` nebo `<iframe>`?**  
A: PDF.js umožňuje full control - touch gestures, progress tracking, custom navigation. Embed/iframe to neumožňují.

**Q: Co když PDF.js selže?**  
A: Viewer zobrazí error message a umožní pokračovat (skip).

**Q: Funguje to offline?**  
A: PDF.js se načte z CDN - potřeba internet. Můžeš ho nahrát lokálně.

**Q: Jak funguje swipe na touch zařízeních?**  
A: Touch gestures detekuje horizontal movement a zavolá `nextPage()`/`previousPage()`.

**Q: Lze použít pinch zoom?**  
A: Zatím ne, ale lze přidat do touch-gestures.js (viz TODO v kódu).

---

## 📞 Support

Pro otázky nebo problémy vytvoř issue.

**Verze:** 3.0.0  
**Datum:** Listopad 2024  
**Autor:** Claude (Anthropic)
