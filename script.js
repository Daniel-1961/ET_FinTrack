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

    // 1.5 Desktop Sidebar Toggle
    const desktopToggle = document.getElementById('desktop-toggle');
    const mainContent = document.querySelector('.main-content');
    
    if (desktopToggle && sidebar && mainContent) {
        desktopToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            
            // Save preference to localStorage
            if (sidebar.classList.contains('collapsed')) {
                localStorage.setItem('sidebar_collapsed', 'true');
            } else {
                localStorage.setItem('sidebar_collapsed', 'false');
            }
        });
        
        // Restore preference on load
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
        }
    }

    // 1.6 Global Edit Profile Drawer Trigger
    const userWidget = document.querySelector('.user-widget');
    const profileDrawer = document.getElementById('drawer-profile');
    if (userWidget && profileDrawer) {
        userWidget.addEventListener('click', () => {
            profileDrawer.style.display = 'flex';
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

    // Auto-open stock drawer if requested via URL param (used by sidebar link)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('drawer') === 'stock') {
        if (typeof openStockDrawer === 'function') {
            setTimeout(openStockDrawer, 100);
        }
    }

    // 3. Theme Toggle Logic
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        const themeIcon = themeToggleBtn.querySelector('i');
        
        // Init icon based on current state
        if (document.documentElement.classList.contains('light-theme') || document.body.classList.contains('light-theme')) {
            themeIcon.className = 'fas fa-sun';
        } else {
            themeIcon.className = 'fas fa-moon';
        }

        themeToggleBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('light-theme');
            document.body.classList.toggle('light-theme');
            
            const isLight = document.body.classList.contains('light-theme');
            localStorage.setItem('fintrack_theme', isLight ? 'light' : 'dark');
            themeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
            
            // Dispatch event so charts can redraw colors
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: isLight ? 'light' : 'dark' }));
        });
    }
});

/**
 * Professional Analytics Chart.js Implementation
 */
let profitTrendChartInstance = null;
let profitProductChartInstance = null;
let salesPieChartInstance = null;

function getChartColors() {
    const isLight = document.documentElement.classList.contains('light-theme') || document.body.classList.contains('light-theme');
    return {
        text: isLight ? '#4B5563' : '#9CA3AF',
        grid: isLight ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)',
        primary: '#10B981',
        primaryGlow: isLight ? 'rgba(16, 185, 129, 0.2)' : 'rgba(16, 185, 129, 0.1)',
        secondary: '#3B82F6',
        palette: ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6', '#F43F5E', '#6366F1']
    };
}

function initializeDashboardCharts() {
    if (!window.analyticsData || typeof Chart === 'undefined') return;

    const colors = getChartColors();
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: colors.text, font: { family: 'Outfit' } } }
        },
        scales: {
            x: { ticks: { color: colors.text }, grid: { color: colors.grid } },
            y: { ticks: { color: colors.text }, grid: { color: colors.grid } }
        }
    };

    // 1. Profit Trend Chart (Line)
    const trendCtx = document.getElementById('profitTrendChart');
    if (trendCtx) {
        profitTrendChartInstance = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: window.analyticsData.profitTrend.daily.labels,
                datasets: [{
                    label: 'Net Profit (ETB)',
                    data: window.analyticsData.profitTrend.daily.data,
                    borderColor: colors.primary,
                    backgroundColor: colors.primaryGlow,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: commonOptions
        });
    }

    // 2. Net Profit per Product (Horizontal Bar)
    const prodCtx = document.getElementById('profitProductChart');
    if (prodCtx) {
        const labels = window.analyticsData.productProfit.map(p => p.name);
        const data = window.analyticsData.productProfit.map(p => p.profit);
        
        profitProductChartInstance = new Chart(prodCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Profit (ETB)',
                    data: data,
                    backgroundColor: colors.secondary,
                    borderRadius: 4
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y'
            }
        });
    }

    // 3. Product Sales Distribution (Doughnut)
    const pieCtx = document.getElementById('salesPieChart');
    if (pieCtx) {
        const labels = window.analyticsData.productVolume.map(p => p.name);
        const data = window.analyticsData.productVolume.map(p => p.volume);
        
        salesPieChartInstance = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.palette,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: colors.text, font: { family: 'Outfit' } } }
                },
                cutout: '70%'
            }
        });
    }
}

// Global function to toggle profit chart periods
window.updateProfitChart = function(period) {
    if (!profitTrendChartInstance || !window.analyticsData) return;
    
    // Update active button styling
    const btns = document.querySelectorAll('.chart-toggles .btn');
    btns.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    // Update data
    const newLabels = window.analyticsData.profitTrend[period].labels;
    const newData = window.analyticsData.profitTrend[period].data;
    
    profitTrendChartInstance.data.labels = newLabels;
    profitTrendChartInstance.data.datasets[0].data = newData;
    profitTrendChartInstance.update();
};

// Listeners for initialization and theme toggling
window.addEventListener('load', initializeDashboardCharts);

window.addEventListener('themeChanged', () => {
    const colors = getChartColors();
    const instances = [profitTrendChartInstance, profitProductChartInstance, salesPieChartInstance];
    
    instances.forEach(chart => {
        if (!chart) return;
        if (chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = colors.text;
        }
        if (chart.options.scales && chart.options.scales.x) {
            chart.options.scales.x.ticks.color = colors.text;
            chart.options.scales.x.grid.color = colors.grid;
            chart.options.scales.y.ticks.color = colors.text;
            chart.options.scales.y.grid.color = colors.grid;
        }
        if (chart === profitTrendChartInstance) {
            chart.data.datasets[0].backgroundColor = colors.primaryGlow;
        }
        chart.update();
    });
});
