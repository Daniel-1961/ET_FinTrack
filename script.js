/**
 * FinTrack ET - Core Shared JavaScript Utilities
 * Implements sidebar responsive toggle, chart drawing engine, translation trigger, and helpers.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebar = document.querySelector('.sidebar');
    const mobileToggle = document.querySelector('.mobile-nav-toggle');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            // Change hamburger icon inside button
            const icon = mobileToggle.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('open')) {
                    icon.className = 'fas fa-times';
                } else {
                    icon.className = 'fas fa-bars';
                }
            }
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && e.target !== mobileToggle) {
                    sidebar.classList.remove('open');
                    const icon = mobileToggle.querySelector('i');
                    if (icon) icon.className = 'fas fa-bars';
                }
            }
        });
    }

    // 2. Global Drawer Close helper
    const closeBtns = document.querySelectorAll('.drawer-close');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const overlay = btn.closest('.drawer-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        });
    });

    // Close drawers on overlay click
    const overlays = document.querySelectorAll('.drawer-overlay');
    overlays.forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.style.display = 'none';
            }
        });
    });
});

/**
 * Custom SVG Line Chart Drawing Engine
 * Renders a premium responsive dual line chart for sales and expenses
 * @param {string} containerId - The HTML element containing the SVG
 * @param {Array<string>} labels - X-axis categories (e.g., months, days)
 * @param {Array<number>} sales - Sales data points
 * @param {Array<number>} expenses - Expenses data points
 */
function renderSVGChart(containerId, labels, sales, expenses) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Clear previous contents
    container.innerHTML = '';

    const width = container.clientWidth || 600;
    const height = 250;
    const paddingLeft = 50;
    const paddingRight = 20;
    const paddingTop = 20;
    const paddingBottom = 40;

    const chartWidth = width - paddingLeft - paddingRight;
    const chartHeight = height - paddingTop - paddingBottom;

    // Find min and max for scaling
    const maxVal = Math.max(...sales, ...expenses, 1000) * 1.1; // Add 10% padding
    const minVal = 0;
    const valRange = maxVal - minVal;

    // Build grid lines
    let gridHTML = '';
    const gridCount = 5;
    for (let i = 0; i <= gridCount; i++) {
        const yVal = minVal + (valRange / gridCount) * i;
        const yPos = paddingTop + chartHeight - (chartHeight * (i / gridCount));
        
        // Grid Line
        gridHTML += `<line class="chart-grid-line" x1="${paddingLeft}" y1="${yPos}" x2="${width - paddingRight}" y2="${yPos}" />`;
        // Grid Label
        gridHTML += `<text x="${paddingLeft - 10}" y="${yPos + 4}" fill="#9CA3AF" font-size="10" text-anchor="end">${Math.round(yVal)}</text>`;
    }

    // Helper to calculate X/Y coordinates
    const getX = (index) => paddingLeft + (chartWidth * (index / (labels.length - 1)));
    const getY = (value) => paddingTop + chartHeight - (chartHeight * ((value - minVal) / valRange));

    // Compile paths
    let salesPath = '';
    let expensesPath = '';
    let salesAreaPath = `M ${getX(0)} ${paddingTop + chartHeight} `;
    let expensesAreaPath = `M ${getX(0)} ${paddingTop + chartHeight} `;
    
    let dotsHTML = '';

    for (let i = 0; i < labels.length; i++) {
        const x = getX(i);
        const ySales = getY(sales[i]);
        const yExpenses = getY(expenses[i]);

        if (i === 0) {
            salesPath += `M ${x} ${ySales}`;
            expensesPath += `M ${x} ${yExpenses}`;
        } else {
            salesPath += ` L ${x} ${ySales}`;
            expensesPath += ` L ${x} ${yExpenses}`;
        }

        salesAreaPath += `L ${x} ${ySales} `;
        expensesAreaPath += `L ${x} ${yExpenses} `;

        // Interactive Dots
        dotsHTML += `
            <circle class="chart-dot-sales" cx="${x}" cy="${ySales}" r="5" data-val="${sales[i]}" />
            <circle class="chart-dot-expenses" cx="${x}" cy="${yExpenses}" r="5" data-val="${expenses[i]}" />
        `;
    }

    salesAreaPath += `L ${getX(labels.length - 1)} ${paddingTop + chartHeight} Z`;
    expensesAreaPath += `L ${getX(labels.length - 1)} ${paddingTop + chartHeight} Z`;

    // Construct SVG HTML
    const svgHTML = `
        <svg class="chart-svg" width="100%" height="100%" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
            <defs>
                <linearGradient id="grad-sales" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                </linearGradient>
                <linearGradient id="grad-expenses" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#EF4444" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
            
            <!-- Axis lines -->
            <line class="chart-axis" x1="${paddingLeft}" y1="${paddingTop}" x2="${paddingLeft}" y2="${paddingTop + chartHeight}" />
            <line class="chart-axis" x1="${paddingLeft}" y1="${paddingTop + chartHeight}" x2="${width - paddingRight}" y2="${paddingTop + chartHeight}" />
            
            <!-- Grid lines & Y-labels -->
            ${gridHTML}
            
            <!-- Area Fills -->
            <path class="chart-area-sales" d="${salesAreaPath}" />
            <path class="chart-area-expenses" d="${expensesAreaPath}" />
            
            <!-- Lines -->
            <path class="chart-line-sales" d="${salesPath}" />
            <path class="chart-line-expenses" d="${expensesPath}" />
            
            <!-- Dots -->
            ${dotsHTML}
        </svg>
    `;

    container.innerHTML = svgHTML;

    // Render X labels under container
    const xLabelsContainer = document.createElement('div');
    xLabelsContainer.className = 'chart-labels-x';
    labels.forEach(label => {
        const span = document.createElement('span');
        span.textContent = label;
        xLabelsContainer.appendChild(span);
    });

    // Check if x labels already exist and replace or append
    const existingLabels = container.nextElementSibling;
    if (existingLabels && existingLabels.className === 'chart-labels-x') {
        existingLabels.replaceWith(xLabelsContainer);
    } else {
        container.parentNode.insertBefore(xLabelsContainer, container.nextSibling);
    }
}
