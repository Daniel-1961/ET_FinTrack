        </main>
    </div>

    <!-- Core JavaScript scripts -->
    <script src="script.js"></script>
    <script>
        // Dictionary matching key templates
        const globalTranslations = {
            en: {
                app_title: "FinTrack ET",
                app_subtitle: "Addis Ababa Retail",
                user_role: "Owner Profile",
                menu_dashboard: "Dashboard",
                menu_transactions: "Ledger",
                menu_credits: "Debtors CRM",
                menu_reports: "Reports & BI",
                
                // Dash translations
                dash_welcome: "Welcome Back!",
                dash_desc: "Here is your business financial summary for this month.",
                btn_record_sale: "Record Sale",
                btn_record_expense: "Record Expense",
                metric_sales: "Total Sales",
                metric_expenses: "Expenses",
                metric_credits: "Active Debts",
                metric_profit: "Net Profit",
                panel_sales_trend: "Sales vs Expenses Trend",
                chart_realtime: "Real-time dynamic visualization",
                panel_urgent_debts: "Urgent Collections",
                panel_recent_tx: "Recent Ledger Entries",
                btn_view_all: "View All",
                
                table_date: "Date",
                table_desc: "Description",
                table_type: "Type",
                table_amount: "Amount",
                table_status: "Status",
                table_category: "Category",
                table_actions: "Actions",
                table_customer: "Customer Name",
                table_phone: "Phone",
                table_location: "Location",
                table_total_debt: "Outstanding Debt",
                table_last_active: "Last Repayment",
                table_percentage: "Percentage",
                
                ledger_title: "Financial Ledger",
                ledger_subtitle: "Complete record of every transaction in your business.",
                filter_all: "All Records",
                filter_sales: "Sales Only",
                filter_expenses: "Expenses Only",
                filter_credits: "Debts & Credits",
                
                crm_title: "Customer Debt Tracker",
                crm_subtitle: "Easily manage your regular customers, record outstanding balances, and trace repayments.",
                btn_add_customer: "Register Customer",
                
                reports_title: "Reports & Business Intelligence",
                reports_subtitle: "Custom advice curated for micro and small businesses operating in Addis Ababa markets.",
                btn_print: "Print Summary",
                bi_advisor: "FinTrack Intelligent Market Advisor",
                panel_expense_share: "Expense Categorization",
                panel_top_debtors: "Top Outstanding Debtors",
                
                form_desc: "Transaction Title / Items purchased",
                form_amount: "Amount (ETB)",
                form_category: "Category",
                form_date: "Transaction Date",
                form_payment_status: "Payment Status",
                status_paid: "Paid (Cash / Telebirr)",
                status_credit: "On Credit (ዕዳ)",
                form_customer_select: "Link to Customer Account",
                form_customer_helper: "Customer not registered? Go to the Debtors CRM tab to register them.",
                btn_save_tx: "Save Transaction",
                
                drawer_customer_title: "Register Customer",
                form_cust_name: "Full Name",
                form_cust_phone: "Phone Number",
                form_cust_shop: "Shop/Business Name",
                form_cust_location: "Location / Addis Subcity",
                btn_save_customer: "Register Customer",
                
                drawer_repay_title: "Record Debt Repayment",
                form_repay_customer_name: "Debtor Customer",
                form_repay_balance: "Outstanding Balance (ETB)",
                form_repay_amount: "Repayment Amount (ETB)",
                form_repay_method: "Repayment Method",
                method_cash: "Cash (በጥሬ ገንዘብ)",
                method_telebirr: "Telebirr (በቴሌብር)",
                form_repay_date: "Repayment Date",
                btn_submit_repay: "Log Repayment",
                
                action_delete: "Delete",
                action_pay: "Repay",
                text_paid: "Paid",
                text_credit: "Unpaid Debt",
                text_expense: "Expense"
            },
            am: {
                app_title: "ፋይናንስ ትራክ",
                app_subtitle: "አዲስ አበባ ንግድ",
                user_role: "የባለቤቱ ገጽ",
                menu_dashboard: "ዳሽቦርድ",
                menu_transactions: "የሂሳብ መዝገብ",
                menu_credits: "የደንበኞች ዕዳ",
                menu_reports: "ሪፖርትና ምክሮች",
                
                dash_welcome: "እንኳን ደህና መጡ!",
                dash_desc: "የዚህ ወር የሱቅዎ የፋይናንስ እንቅስቃሴ ማጠቃለያ ይህንን ይመስላል።",
                btn_record_sale: "ሽያጭ መዝግብ",
                btn_record_expense: "ወጪ መዝግብ",
                metric_sales: "ጠቅላላ ሽያጭ",
                metric_expenses: "ጠቅላላ ወጪ",
                metric_credits: "ያልተሰበሰበ ዕዳ",
                metric_profit: "የተጣራ ትርፍ",
                panel_sales_trend: "የሽያጭ እና ወጪ ንፅፅር",
                chart_realtime: "ፈጣን እና ቀጥታ መረጃዎችን ማሳያ",
                panel_urgent_debts: "አስቸኳይ የሚሰበሰቡ ዕዳዎች",
                panel_recent_tx: "የቅርብ ጊዜ የሂሳብ መዝገቦች",
                btn_view_all: "ሁሉንም አሳይ",
                
                table_date: "ቀን",
                table_desc: "ዝርዝር",
                table_type: "ዓይነት",
                table_amount: "መጠን",
                table_status: "ሁኔታ",
                table_category: "ምድብ",
                table_actions: "ድርጊቶች",
                table_customer: "የደንበኛ ስም",
                table_phone: "ስልክ ቁጥር",
                table_location: "አድራሻ",
                table_total_debt: "ያለበት ዕዳ",
                table_last_active: "የመጨረሻ ክፍያ",
                table_percentage: "ድርሻ (%)",
                
                ledger_title: "የሂሳብ መዝገብ",
                ledger_subtitle: "የሁሉም የሽያጭ እና የዕለት ተዕለት ወጪዎችዎ ዝርዝር መዝገብ።",
                filter_all: "ሁሉንም መዝገብ",
                filter_sales: "ሽያጮች ብቻ",
                filter_expenses: "ወጪዎች ብቻ",
                filter_credits: "ዕዳ እና ብድሮች",
                
                crm_title: "የደንበኞች ዕዳ መቆጣጠሪያ",
                crm_subtitle: "ቋሚ ደንበኞችን ይመዝግቡ፣ ብድሮችን ይከታተሉ፣ የዕዳ መክፈያዎችን ይመዝግቡ።",
                btn_add_customer: "አዲስ ደንበኛ መዝግብ",
                
                reports_title: "ሪፖርትና የገበያ መረጃዎች",
                reports_subtitle: "ለአዲስ አበባ ጥቃቅን እና አነስተኛ ነጋዴዎች የተዘጋጁ ልዩ ምክሮች።",
                btn_print: "ሪፖርቱን አትም",
                bi_advisor: "የፋይናንስ ትራክ የገበያ መካሪ",
                panel_expense_share: "የወጪዎች ምድብ ክፍፍል",
                panel_top_debtors: "ትልቅ ዕዳ ያለባቸው ደንበኞች",
                
                form_desc: "የግብይቱ ርዕስ / የተሸጡ ዕቃዎች",
                form_amount: "የገንዘብ መጠን (ብር)",
                form_category: "ምድብ",
                form_date: "የተከናወነበት ቀን",
                form_payment_status: "የክፍያ ሁኔታ",
                status_paid: "ተከፍሏል (በጥሬ ገንዘብ / ቴሌብር)",
                status_credit: "በብድር (ዕዳ)",
                form_customer_select: "ደንበኛ ምረጥ",
                form_customer_helper: "ደንበኛው አልተመዘገበም? «የደንበኞች ዕዳ» ገጽ ላይ ሄደው ይመዝግቡት።",
                btn_save_tx: "ግብይቱን መዝግብ",
                
                drawer_customer_title: "አዲስ ደንበኛ መመዝገቢያ",
                form_cust_name: "ሙሉ ስም",
                form_cust_phone: "ስልክ ቁጥር",
                form_cust_shop: "የሱቅ ስም",
                form_cust_location: "አድራሻ / ክፍለ ከተማ",
                btn_save_customer: "ደንበኛውን መዝግብ",
                
                drawer_repay_title: "የዕዳ ክፍያ መመዝገቢያ",
                form_repay_customer_name: "የደንበኛው ስም",
                form_repay_balance: "ያለበት ጠቅላላ ዕዳ (ብር)",
                form_repay_amount: "የተከፈለ መጠን (ብር)",
                form_repay_method: "የክፍያ መንገድ",
                method_cash: "በጥሬ ገንዘብ (Cash)",
                method_telebirr: "በቴሌብር (Telebirr)",
                form_repay_date: "የተከፈለበት ቀን",
                btn_submit_repay: "ክፍያውን መዝግብ",
                
                action_delete: "ሰርዝ",
                action_pay: "ዕዳ ክፈል",
                text_paid: "ተከፍሏል",
                text_credit: "ዕዳ (ያልተከፈለ)",
                text_expense: "ወጪ"
            }
        };

        function switchPHPLanguage(lang) {
            localStorage.setItem('fintrack_lang', lang);
            
            document.querySelectorAll('[data-localize]').forEach(el => {
                const key = el.getAttribute('data-localize');
                if (globalTranslations[lang] && globalTranslations[lang][key]) {
                    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                        el.placeholder = globalTranslations[lang][key];
                    } else {
                        el.textContent = globalTranslations[lang][key];
                    }
                }
            });

            // Adjust active styles
            const btnEn = document.getElementById('btn-php-en');
            const btnAm = document.getElementById('btn-php-am');
            if (btnEn && btnAm) {
                btnEn.classList.toggle('active', lang === 'en');
                btnAm.classList.toggle('active', lang === 'am');
            }

            // Dispatch global event so page specific drawings can adjust
            const event = new CustomEvent('langChanged', { detail: lang });
            window.dispatchEvent(event);
        }

        // Initialize persistent languages
        window.addEventListener('DOMContentLoaded', () => {
            const savedLang = localStorage.getItem('fintrack_lang') || 'en';
            switchPHPLanguage(savedLang);

            const btnEn = document.getElementById('btn-php-en');
            const btnAm = document.getElementById('btn-php-am');
            if (btnEn) btnEn.addEventListener('click', () => switchPHPLanguage('en'));
            if (btnAm) btnAm.addEventListener('click', () => switchPHPLanguage('am'));
        });
    </script>
</body>
</html>
