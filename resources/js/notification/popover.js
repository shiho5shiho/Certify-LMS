/**
 * TopBar 通知ベルのポップオーバー(受講生・コーチ)。
 * ベルのクリックで通知 JSON API(Sanctum Cookie 認証)から最新 20 件を取得して表示し、
 * 個別既読 + 遷移 / 全件既読 / 全件・未読タブ切替 / 未読バッジの増減を行う。
 *
 * 管理者はポップオーバーの対象外のため、ベルのクリック時にロールを確認し、
 * 管理者の場合はポップオーバーの処理(取得・タブ・既読化)を初期化しない。
 *
 * 関連: resources/views/layouts/_partials/topbar.blade.php
 *       resources/views/notifications/_partials/notification-popover.blade.php
 */

import { getJson } from '../utils/fetch-json';

const API = {
    user: '/api/user',
    csrfCookie: '/sanctum/csrf-cookie',
    notifications: '/api/v1/notifications',
    markAsRead: (id) => `/api/v1/notifications/${id}/read`,
    markAllAsRead: '/api/v1/notifications/read-all',
};

const MESSAGES = {
    fetchFailed: '通知を取得できませんでした。',
    markFailed: '通知を既読にできませんでした。',
};

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    const trigger = root?.querySelector('[data-notification-popover-trigger]');
    const panel = root?.querySelector('[data-notification-popover-panel]');
    if (!root || !trigger || !panel) return;

    let role = null;
    let popover = null;

    trigger.addEventListener('click', async () => {
        role ??= await fetchRole();
        // 管理者はポップオーバー機能の対象外(ベルは押しても何も起きない)
        if (role === 'admin') return;

        popover ??= setupPopover(root, trigger, panel);
        popover.toggle();
    });
}

async function fetchRole() {
    try {
        const user = await getJson(API.user);
        return user.role;
    } catch {
        return null;
    }
}

/**
 * ポップオーバーの処理(取得・タブ・既読化・開閉)を初期化する。受講生・コーチの初回クリック時に 1 度だけ呼ぶ。
 */
function setupPopover(root, trigger, panel) {
    const items = panel.querySelector('[data-notification-popover-items]');
    const tabs = panel.querySelectorAll('[data-notification-popover-tab]');
    let currentTab = 'all';
    let latestRequestId = 0;
    // タブの未読件数は Blade では 0 固定のため、サーバーで算出済みのバッジの値で初期化する(取得失敗時も件数がずれないように)
    const badge = root.querySelector('[data-notification-popover-badge]');
    const tabCount = panel.querySelector('[data-notification-popover-unread-count]');
    if (badge && tabCount) tabCount.textContent = badge.textContent.trim();

    const isOpen = () => trigger.getAttribute('aria-expanded') === 'true';

    async function load() {
        // タブを素早く切り替えたとき、古いレスポンスで上書きしないよう最新のリクエストだけを反映する
        const requestId = ++latestRequestId;
        hideError(panel);
        renderRows(panel, []);
        toggle(panel, '[data-notification-popover-empty]', false);
        toggle(panel, '[data-notification-popover-loading]', true);

        try {
            const response = await getJson(`${API.notifications}?tab=${currentTab}`);
            if (requestId !== latestRequestId) return;
            renderRows(panel, response.data);
            updateUnreadCount(root, response.meta.unread_count);
        } catch {
            if (requestId !== latestRequestId) return;
            showError(panel, MESSAGES.fetchFailed);
        } finally {
            if (requestId === latestRequestId) {
                toggle(panel, '[data-notification-popover-loading]', false);
            }
        }
    }

    // 全件 / 未読タブの切り替え
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            currentTab = tab.dataset.notificationPopoverTab;
            tabs.forEach((other) => other.setAttribute('aria-selected', String(other === tab)));
            load();
        });
    });

    // 通知行のクリック: 既読化してから対応する画面へ移動する(行は後から作られるため、リスト側で受け取る)
    items.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-notification-popover-row]');
        if (!row) return;
        event.preventDefault();

        try {
            const response = await postWithCsrf(API.markAsRead(row.dataset.notificationId));
            updateUnreadCount(root, response.meta.unread_count);
            window.location.href = response.data.url;
        } catch {
            showError(panel, MESSAGES.markFailed);
        }
    });

    // 全件既読
    panel.querySelector('[data-notification-popover-mark-all]')?.addEventListener('click', async () => {
        try {
            const response = await postWithCsrf(API.markAllAsRead);
            updateUnreadCount(root, response.meta.unread_count);
            load();
        } catch {
            showError(panel, MESSAGES.markFailed);
        }
    });

    // 外側クリック / Esc で閉じる
    document.addEventListener('click', (event) => {
        if (isOpen() && !root.contains(event.target)) closePanel(trigger, panel);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            closePanel(trigger, panel);
            trigger.focus();
        }
    });

    return {
        toggle() {
            if (isOpen()) {
                closePanel(trigger, panel);
                return;
            }
            openPanel(trigger, panel);
            load();
        },
    };
}

function openPanel(trigger, panel) {
    panel.classList.remove('hidden');
    panel.style.display = 'flex';
    // display を切り替えた直後にクラスを外すとアニメーションしないため、一度レイアウトを確定させる
    void panel.offsetWidth;
    panel.classList.remove('opacity-0', '-translate-y-1');
    trigger.setAttribute('aria-expanded', 'true');
}

function closePanel(trigger, panel) {
    panel.classList.add('opacity-0', '-translate-y-1');
    trigger.setAttribute('aria-expanded', 'false');
    setTimeout(() => {
        // 閉じるアニメーション中に開き直された場合は隠さない
        if (trigger.getAttribute('aria-expanded') === 'true') return;
        panel.classList.add('hidden');
        panel.style.display = 'none';
    }, 150);
}

function renderRows(panel, notifications) {
    const template = panel.querySelector('[data-notification-popover-row-template]');
    const items = panel.querySelector('[data-notification-popover-items]');

    const rows = notifications.map((notification) => {
        const item = template.content.firstElementChild.cloneNode(true);
        const row = item.querySelector('[data-notification-popover-row]');
        const unread = String(!notification.is_read);

        row.href = notification.url;
        row.dataset.notificationId = notification.id;
        // テンプレートの未読背景クラス(aria-[data-unread=true]:...)は aria-data-unread 属性に反応する
        row.setAttribute('aria-data-unread', unread);
        item.querySelector('[data-notification-popover-row-dot]').classList.toggle('invisible', notification.is_read);
        // 通知の文言はユーザーの投稿を含むため、HTML として解釈されないよう textContent で入れる
        item.querySelector('[data-notification-popover-row-title]').textContent = notification.title;
        item.querySelector('[data-notification-popover-row-message]').textContent = notification.message;
        item.querySelector('[data-notification-popover-row-time]').textContent = notification.created_at_human;

        return item;
    });

    items.replaceChildren(...rows);
    toggle(panel, '[data-notification-popover-empty]', notifications.length === 0);
}

function updateUnreadCount(root, count) {
    const label = count > 99 ? '99+' : String(count);
    const badge = root.querySelector('[data-notification-popover-badge]');
    const tabCount = root.querySelector('[data-notification-popover-unread-count]');

    if (badge) {
        badge.textContent = label;
        badge.classList.toggle('hidden', count <= 0);
    }
    if (tabCount) tabCount.textContent = label;
    root.querySelector('[data-notification-popover-trigger]')?.setAttribute('aria-label', `通知 (${count} 件未読)`);
}

function toggle(panel, selector, visible) {
    panel.querySelector(selector)?.classList.toggle('hidden', !visible);
}

function errorElement(panel) {
    let element = panel.querySelector('[data-notification-popover-error]');
    if (!element) {
        element = document.createElement('p');
        element.dataset.notificationPopoverError = '';
        element.setAttribute('role', 'alert');
        element.className = 'hidden px-4 py-3 text-center text-xs text-danger-600';
        panel.querySelector('[data-notification-popover-list]').prepend(element);
    }
    return element;
}

function showError(panel, message) {
    const element = errorElement(panel);
    element.textContent = message;
    element.classList.remove('hidden');
}

function hideError(panel) {
    errorElement(panel).classList.add('hidden');
}

function readCookie(name) {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));
    return cookie ? decodeURIComponent(cookie.slice(name.length + 1)) : '';
}

/**
 * Sanctum SPA 認証の CSRF 対策: POST の前に /sanctum/csrf-cookie で XSRF-TOKEN Cookie を発行し、
 * その値を X-XSRF-TOKEN ヘッダで送り返す。
 */
async function postWithCsrf(url) {
    await fetch(API.csrfCookie, { credentials: 'same-origin', headers: { Accept: 'application/json' } });

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
        },
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    return response.json();
}
