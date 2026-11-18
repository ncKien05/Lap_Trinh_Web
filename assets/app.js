(() => {
    let readRequest;
    let saving = false;
    let applying = false;
    const loadedScripts = new Set([...document.scripts].map(script => script.src).filter(Boolean));
    const appScript = new URL('assets/app.js', document.baseURI).href;

    function showError(message) {
        let alert = document.getElementById('app-feedback');
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'app-feedback';
            alert.className = 'alert alert-danger m-3';
            alert.setAttribute('role', 'alert');
            document.body.prepend(alert);
        }
        alert.textContent = message;
    }

    async function runScripts(page, orderFormOnly = false) {
        const scripts = [...page.querySelectorAll('script')];
        for (const original of scripts) {
            if (orderFormOnly && !original.src.endsWith('/assets/order-form.js')) continue;
            if (original.src === appScript) continue;
            if (original.src && loadedScripts.has(original.src) && !original.src.endsWith('/assets/order-form.js')) continue;
            const script = document.createElement('script');
            if (original.src) {
                script.src = original.src;
                await new Promise((resolve, reject) => {
                    script.onload = resolve;
                    script.onerror = () => reject(new Error('Không tải được mã giao diện. Vui lòng tải lại trang.'));
                    document.body.append(script);
                });
                loadedScripts.add(original.src);
            } else {
                script.textContent = '(() => {\n' + original.textContent + '\n})();';
                document.body.append(script);
            }
            script.remove();
        }
    }

    function updateStyles(page) {
        document.head.querySelectorAll('style').forEach(style => style.remove());
        page.head.querySelectorAll('style').forEach(style => document.head.append(style.cloneNode(true)));
        for (const link of page.head.querySelectorAll('link[rel="stylesheet"]')) {
            if (![...document.styleSheets].some(sheet => sheet.href === link.href)) {
                document.head.append(link.cloneNode(true));
            }
        }
    }

    function clearOverlays() {
        document.querySelectorAll('.modal, .offcanvas').forEach(element => {
            bootstrap.Modal.getInstance(element)?.dispose();
            bootstrap.Offcanvas.getInstance(element)?.dispose();
        });
        document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(element => element.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function renderPage(page, url, replaceHistory) {
        document.querySelectorAll('canvas').forEach(canvas => window.Chart?.getChart(canvas)?.destroy());
        clearOverlays();
        updateStyles(page);
        page.body.querySelectorAll('script').forEach(script => script.remove());
        // Scripts are kept separately before importing the new body.
        document.body.replaceWith(document.importNode(page.body, true));
        document.title = page.title;
        history[replaceHistory ? 'replaceState' : 'pushState'](null, '', url);
        window.scrollTo(0, 0);
    }

    async function loadPage(url, options = {}) {
        if (saving || applying) return;
        readRequest?.abort();
        const controller = new AbortController();
        readRequest = controller;
        const isPost = Boolean(options.body);
        if (isPost) saving = true;
        document.documentElement.classList.add('app-loading');
        try {
            const response = await fetch(url, {
                method: isPost ? 'POST' : 'GET',
                body: options.body,
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                signal: controller.signal
            });
            const html = await response.text();
            if (controller.signal.aborted) return;
            const page = new DOMParser().parseFromString(html, 'text/html');
            if (!response.ok || !page.title) throw new Error('Không thể hoàn tất yêu cầu. Vui lòng thử lại.');
            const scripts = document.createElement('div');
            page.querySelectorAll('script').forEach(script => scripts.append(script.cloneNode(true)));
            applying = true;
            const path = new URL(response.url).pathname;
            const orderModal = document.getElementById('orderFormModal');
            const hasOrderForm = /\/(create|edit)\.php$/.test(path);
            if ((options.modal || options.fromModal) && hasOrderForm) {
                const formContent = page.querySelector('.container');
                if (!formContent || !orderModal) throw new Error('Không thể mở form đơn hàng.');
                orderModal.querySelector('.modal-body').replaceChildren(...[...formContent.children].map(child => document.importNode(child, true)));
                orderModal.querySelector('.modal-title').textContent = path.endsWith('/edit.php') ? 'Cập nhật đơn hàng' : 'Tạo đơn hàng';
                await runScripts(scripts, true);
                for (const openModal of document.querySelectorAll('.modal.show')) {
                    if (openModal === orderModal) continue;
                    await new Promise(resolve => {
                        openModal.addEventListener('hidden.bs.modal', resolve, {once: true});
                        bootstrap.Modal.getInstance(openModal).hide();
                    });
                }
                bootstrap.Modal.getOrCreateInstance(orderModal).show();
                orderModal.querySelector('input:not([type="hidden"])')?.focus();
            } else {
                renderPage(page, response.url, options.replace || isPost);
                await runScripts(scripts);
            }
        } catch (error) {
            if (error.name !== 'AbortError') showError(error.message);
        } finally {
            if (readRequest === controller) document.documentElement.classList.remove('app-loading');
            if (isPost) saving = false;
            applying = false;
        }
    }

    document.addEventListener('click', event => {
        const close = event.target.closest('[data-close-order-form]');
        if (close && close.closest('#orderFormModal')) {
            event.preventDefault();
            bootstrap.Modal.getInstance(document.getElementById('orderFormModal'))?.hide();
            return;
        }
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link.target || link.hasAttribute('download') || link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-no-ajax')) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || !url.pathname.endsWith('.php') || link.getAttribute('href').startsWith('#')) return;
        event.preventDefault();
        loadPage(url.href, {modal: link.hasAttribute('data-order-form')});
    });

    document.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        const form = event.target;
        if (form.hasAttribute('data-no-ajax')) return;
        event.preventDefault();
        if (saving) return;
        const body = new FormData(form);
        if (event.submitter?.name) body.set(event.submitter.name, event.submitter.value);
        const url = new URL(form.action || location.href);
        const options = {fromModal: Boolean(form.closest('#orderFormModal'))};
        if (form.method.toLowerCase() === 'post') {
            options.body = body;
        } else {
            url.search = new URLSearchParams(body).toString();
        }
        loadPage(url.href, options);
    });

    window.addEventListener('popstate', () => loadPage(location.href, {replace: true}));
})();
