/**
 * SAW PDF Viewer
 * 
 * PDF.js wrapper for training map viewer
 * Features: touch gestures, page navigation, progress tracking
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * Dependencies:
 * - PDF.js (loaded from CDN)
 * - SAWTouchGestures (touch-gestures.js)
 * 
 * Usage:
 * const viewer = new SAWPDFViewer({
 *     pdfUrl: '/path/to/map.pdf',
 *     canvasId: 'pdf-canvas',
 *     onComplete: function() {
 *         console.log('Last page viewed');
 *     }
 * });
 */

(function(window) {
    'use strict';
    
    /**
     * PDF Viewer Class
     * 
     * @param {Object} options Configuration options
     */
    function SAWPDFViewer(options) {
        // Validate options
        if (!options || !options.pdfUrl) {
            console.error('[SAW PDF Viewer] Error: pdfUrl is required');
            return;
        }
        
        // Configuration
        this.pdfUrl = options.pdfUrl;
        this.canvasId = options.canvasId || 'pdf-canvas';
        this.onComplete = options.onComplete || null;
        this.onPageChange = options.onPageChange || null;
        this.debug = options.debug || false;
        
        // State
        this.pdfDoc = null;
        this.currentPage = 1;
        this.totalPages = 0;
        this.rendering = false;
        this.scale = this.getResponsiveScale();
        this.gestureHandler = null;
        this.viewedPages = new Set();
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize PDF viewer
     */
    SAWPDFViewer.prototype.init = async function() {
        try {
            // Check if PDF.js is loaded
            if (typeof pdfjsLib === 'undefined') {
                console.error('[SAW PDF Viewer] Error: PDF.js not loaded');
                await this.loadPDFJS();
            }
            
            // Set PDF.js worker
            pdfjsLib.GlobalWorkerOptions.workerSrc = 
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            
            // Load PDF document
            await this.loadDocument();
            
            // Setup controls
            this.setupControls();
            
            // Setup touch gestures
            this.setupGestures();
            
            // Setup keyboard navigation
            this.setupKeyboard();
            
            if (this.debug) {
                console.log('[SAW PDF Viewer] Initialized successfully');
            }
            
        } catch (error) {
            console.error('[SAW PDF Viewer] Initialization error:', error);
            this.showError('Chyba při načítání PDF dokumentu');
        }
    };
    
    /**
     * Load PDF.js library from CDN
     */
    SAWPDFViewer.prototype.loadPDFJS = function() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    };
    
    /**
     * Load PDF document
     */
    SAWPDFViewer.prototype.loadDocument = async function() {
        try {
            const loadingTask = pdfjsLib.getDocument(this.pdfUrl);
            
            // Show loading progress
            loadingTask.onProgress = (progress) => {
                const percent = (progress.loaded / progress.total * 100).toFixed(0);
                this.updateLoadingProgress(percent);
            };
            
            this.pdfDoc = await loadingTask.promise;
            this.totalPages = this.pdfDoc.numPages;
            
            if (this.debug) {
                console.log('[SAW PDF Viewer] PDF loaded:', {
                    totalPages: this.totalPages,
                    url: this.pdfUrl
                });
            }
            
            // Render first page
            await this.renderPage(this.currentPage);
            
        } catch (error) {
            console.error('[SAW PDF Viewer] Document load error:', error);
            throw error;
        }
    };
    
    /**
     * Get responsive scale based on screen size
     */
    SAWPDFViewer.prototype.getResponsiveScale = function() {
        const width = window.innerWidth;
        
        if (width < 768) {
            return 1.0;  // Mobile
        } else if (width < 1024) {
            return 1.5;  // Tablet
        } else {
            return 2.0;  // Desktop
        }
    };
    
    /**
     * Render PDF page to canvas
     * 
     * @param {number} pageNum Page number to render
     */
    SAWPDFViewer.prototype.renderPage = async function(pageNum) {
        if (this.rendering) {
            if (this.debug) {
                console.log('[SAW PDF Viewer] Already rendering, skipping');
            }
            return;
        }
        
        this.rendering = true;
        
        try {
            // Get page
            const page = await this.pdfDoc.getPage(pageNum);
            
            // Get canvas
            const canvas = document.getElementById(this.canvasId);
            if (!canvas) {
                throw new Error('Canvas element not found: ' + this.canvasId);
            }
            
            const ctx = canvas.getContext('2d');
            
            // Calculate scale to fit container
            const container = canvas.parentElement;
            const containerWidth = container.clientWidth - 32; // Padding
            
            const viewport = page.getViewport({ scale: this.scale });
            const scale = containerWidth / viewport.width;
            const scaledViewport = page.getViewport({ scale: this.scale * scale });
            
            // Set canvas dimensions
            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;
            
            // Render page
            const renderContext = {
                canvasContext: ctx,
                viewport: scaledViewport
            };
            
            await page.render(renderContext).promise;
            
            // Update state
            this.currentPage = pageNum;
            this.viewedPages.add(pageNum);
            
            // Update UI
            this.updateUI();
            
            // Track completion
            this.checkCompletion();
            
            // Trigger callback
            if (typeof this.onPageChange === 'function') {
                this.onPageChange({
                    currentPage: this.currentPage,
                    totalPages: this.totalPages,
                    viewedPages: this.viewedPages.size
                });
            }
            
            if (this.debug) {
                console.log('[SAW PDF Viewer] Rendered page:', pageNum);
            }
            
        } catch (error) {
            console.error('[SAW PDF Viewer] Render error:', error);
            this.showError('Chyba při zobrazení stránky');
        } finally {
            this.rendering = false;
        }
    };
    
    /**
     * Setup navigation controls
     */
    SAWPDFViewer.prototype.setupControls = function() {
        // Previous button
        const prevBtn = document.getElementById('pdf-prev');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.previousPage());
        }
        
        // Next button
        const nextBtn = document.getElementById('pdf-next');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextPage());
        }
        
        if (this.debug) {
            console.log('[SAW PDF Viewer] Controls setup complete');
        }
    };
    
    /**
     * Setup touch gestures
     */
    SAWPDFViewer.prototype.setupGestures = function() {
        const canvas = document.getElementById(this.canvasId);
        if (!canvas || typeof SAWTouchGestures === 'undefined') {
            if (this.debug) {
                console.log('[SAW PDF Viewer] Touch gestures not available');
            }
            return;
        }
        
        this.gestureHandler = new SAWTouchGestures(canvas, {
            onSwipeLeft: () => this.nextPage(),
            onSwipeRight: () => this.previousPage(),
            preventScroll: true,
            swipeThreshold: 50,
            debug: this.debug
        });
        
        if (this.debug) {
            console.log('[SAW PDF Viewer] Touch gestures enabled');
        }
    };
    
    /**
     * Setup keyboard navigation
     */
    SAWPDFViewer.prototype.setupKeyboard = function() {
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    this.previousPage();
                    e.preventDefault();
                    break;
                case 'ArrowRight':
                    this.nextPage();
                    e.preventDefault();
                    break;
            }
        });
        
        if (this.debug) {
            console.log('[SAW PDF Viewer] Keyboard navigation enabled');
        }
    };
    
    /**
     * Go to next page
     */
    SAWPDFViewer.prototype.nextPage = function() {
        if (this.currentPage < this.totalPages) {
            this.renderPage(this.currentPage + 1);
        }
    };
    
    /**
     * Go to previous page
     */
    SAWPDFViewer.prototype.previousPage = function() {
        if (this.currentPage > 1) {
            this.renderPage(this.currentPage - 1);
        }
    };
    
    /**
     * Go to specific page
     * 
     * @param {number} pageNum Page number
     */
    SAWPDFViewer.prototype.goToPage = function(pageNum) {
        if (pageNum >= 1 && pageNum <= this.totalPages) {
            this.renderPage(pageNum);
        }
    };
    
    /**
     * Update UI elements
     */
    SAWPDFViewer.prototype.updateUI = function() {
        // Update page indicator
        const indicator = document.getElementById('pdf-page-indicator');
        if (indicator) {
            indicator.textContent = `${this.currentPage} / ${this.totalPages}`;
        }
        
        // Update navigation buttons
        const prevBtn = document.getElementById('pdf-prev');
        const nextBtn = document.getElementById('pdf-next');
        
        if (prevBtn) {
            prevBtn.disabled = (this.currentPage === 1);
        }
        
        if (nextBtn) {
            nextBtn.disabled = (this.currentPage === this.totalPages);
        }
        
        // Update progress
        const progress = (this.viewedPages.size / this.totalPages * 100).toFixed(0);
        const progressText = document.getElementById('pdf-progress-text');
        if (progressText) {
            progressText.textContent = progress + '%';
        }
    };
    
    /**
     * Check if all pages have been viewed
     */
    SAWPDFViewer.prototype.checkCompletion = function() {
        if (this.viewedPages.size === this.totalPages && typeof this.onComplete === 'function') {
            if (this.debug) {
                console.log('[SAW PDF Viewer] All pages viewed - triggering completion');
            }
            
            this.onComplete({
                totalPages: this.totalPages,
                viewedPages: this.viewedPages.size
            });
        }
    };
    
    /**
     * Update loading progress
     * 
     * @param {number} percent Progress percentage
     */
    SAWPDFViewer.prototype.updateLoadingProgress = function(percent) {
        const progressBar = document.getElementById('pdf-loading-progress');
        if (progressBar) {
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
    };
    
    /**
     * Show error message
     * 
     * @param {string} message Error message
     */
    SAWPDFViewer.prototype.showError = function(message) {
        const canvas = document.getElementById(this.canvasId);
        if (canvas) {
            const container = canvas.parentElement;
            container.innerHTML = `
                <div class="saw-error">
                    <div class="saw-error-icon">⚠️</div>
                    <div class="saw-error-message">${message}</div>
                </div>
            `;
        }
    };
    
    /**
     * Destroy viewer
     * Clean up resources and event listeners
     */
    SAWPDFViewer.prototype.destroy = function() {
        // Destroy gesture handler
        if (this.gestureHandler) {
            this.gestureHandler.destroy();
        }
        
        // Clear PDF document
        if (this.pdfDoc) {
            this.pdfDoc.destroy();
        }
        
        if (this.debug) {
            console.log('[SAW PDF Viewer] Destroyed');
        }
    };
    
    // Export to global scope
    window.SAWPDFViewer = SAWPDFViewer;
    
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = SAWPDFViewer;
    }
    
})(window);

/**
 * Usage Example:
 * 
 * <canvas id="pdf-canvas"></canvas>
 * <div id="pdf-page-indicator"></div>
 * <button id="pdf-prev">Previous</button>
 * <button id="pdf-next">Next</button>
 * 
 * <script>
 * const viewer = new SAWPDFViewer({
 *     pdfUrl: '/path/to/map.pdf',
 *     canvasId: 'pdf-canvas',
 *     debug: true,
 *     onComplete: function(data) {
 *         console.log('All pages viewed!');
 *         // Enable continue button
 *         document.getElementById('continue-btn').disabled = false;
 *     },
 *     onPageChange: function(data) {
 *         console.log('Page:', data.currentPage);
 *     }
 * });
 * </script>
 */
