<?php
// ============================================================
// public/medical_ai.php — Standalone Medical AI Chat Page
//
// Bonus feature only. Does not affect core midterm modules.
// Uses modules/ai/chat.php for AI replies + session chat history.
// ============================================================
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Medical AI</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; AI Tools &rsaquo; Medical AI</div>
    </div>
</div>

<div class="medical-ai-shell">
    <aside class="medical-ai-history">
        <div class="medical-ai-history-head">
            <h5><svg data-lucide="bot" width="17" height="17"></svg>&nbsp;Chat History</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="aiNewChatBtn">
                <svg data-lucide="plus"></svg> New Chat
            </button>
        </div>
        <div class="medical-ai-history-search">
            <i class="bi bi-search"></i>
            <input type="text" id="aiSearchInput" class="form-control flat-input" placeholder="Search chats...">
        </div>
        <div id="aiHistoryList" class="medical-ai-history-list"></div>
    </aside>

    <section class="medical-ai-chat is-empty" id="aiChatPane">
        <div class="medical-ai-chat-head">
            <div>
                <h5 id="aiThreadTitle">New Chat</h5>
                <p>AI-generated information for reference only.</p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" id="aiDeleteChatBtn">
                <svg data-lucide="trash-2"></svg> Delete Chat
            </button>
        </div>

        <div class="medical-ai-hero">
            <h3>What can I help with?</h3>
            <p>Ask a medical question for reference support.</p>
        </div>

        <div id="aiChatMessages" class="medical-ai-messages">
            <div class="medical-ai-msg ai">
                <div class="bubble">
                    Hello! Ask me anything about symptoms, medications, procedures, or clinical workflow.
                </div>
            </div>
        </div>

        <div class="medical-ai-composer">
            <textarea id="aiChatInputPage" class="form-control flat-input" rows="2" placeholder="Message Medical AI..."></textarea>
            <button type="button" class="btn btn-primary" id="aiSendBtnPage">Send</button>
        </div>

        <div class="medical-ai-suggestions" id="aiSuggestions">
            <button type="button" class="medical-ai-suggest">Explain common signs of dehydration simply.</button>
            <button type="button" class="medical-ai-suggest">Create a patient follow-up reminder script.</button>
            <button type="button" class="medical-ai-suggest">Summarize fever first-aid advice for adults.</button>
        </div>
    </section>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
(function () {
    const endpoint = '/clinic_1/modules/ai/chat.php';
    const historyList = document.getElementById('aiHistoryList');
    const messagesBox = document.getElementById('aiChatMessages');
    const input = document.getElementById('aiChatInputPage');
    const sendBtn = document.getElementById('aiSendBtnPage');
    const newChatBtn = document.getElementById('aiNewChatBtn');
    const deleteChatBtn = document.getElementById('aiDeleteChatBtn');
    const searchInput = document.getElementById('aiSearchInput');
    const threadTitle = document.getElementById('aiThreadTitle');
    const chatPane = document.getElementById('aiChatPane');
    const suggestions = document.getElementById('aiSuggestions');

    let threads = [];
    let currentThreadId = '';
    let loading = false;

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function post(payload) {
        const form = new FormData();
        Object.keys(payload).forEach(function (key) {
            form.append(key, payload[key]);
        });
        return fetch(endpoint, { method: 'POST', body: form }).then(function (r) { return r.json(); });
    }

    function renderHistory() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const rows = threads.filter(function (t) {
            if (!q) return true;
            return (t.title || '').toLowerCase().includes(q) || (t.preview || '').toLowerCase().includes(q);
        });

        if (!rows.length) {
            historyList.innerHTML = '<div class="medical-ai-empty">No chats yet.</div>';
            return;
        }

        historyList.innerHTML = rows.map(function (t) {
            const active = t.id === currentThreadId ? 'is-active' : '';
            return '' +
                '<button type="button" class="medical-ai-thread ' + active + '" data-id="' + escHtml(t.id) + '">' +
                '  <strong>' + escHtml(t.title || 'New Chat') + '</strong>' +
                '  <span>' + escHtml(t.preview || '') + '</span>' +
                '</button>';
        }).join('');
    }

    function renderMessages(thread) {
        const msgs = Array.isArray(thread.messages) ? thread.messages : [];
        threadTitle.textContent = thread.title || 'New Chat';

        if (!msgs.length) {
            messagesBox.innerHTML = '<div class="medical-ai-msg ai"><div class="bubble">Start your first question for this chat.</div></div>';
            chatPane.classList.add('is-empty');
            return;
        }

        chatPane.classList.remove('is-empty');
        messagesBox.innerHTML = msgs.map(function (m) {
            const isUser = m.role === 'user';
            return '' +
                '<div class="medical-ai-msg ' + (isUser ? 'user' : 'ai') + '">' +
                '  <div class="bubble">' + escHtml(m.text || '') + '</div>' +
                '</div>';
        }).join('');
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function showTyping() {
        const node = document.createElement('div');
        node.className = 'medical-ai-msg ai';
        node.id = 'aiTyping';
        node.innerHTML = '<div class="bubble"><span class="medical-ai-dot"></span><span class="medical-ai-dot"></span><span class="medical-ai-dot"></span></div>';
        messagesBox.appendChild(node);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function hideTyping() {
        const node = document.getElementById('aiTyping');
        if (node) node.remove();
    }

    function appendUserMessage(text) {
        chatPane.classList.remove('is-empty');
        const node = document.createElement('div');
        node.className = 'medical-ai-msg user';
        node.innerHTML = '<div class="bubble">' + escHtml(text) + '</div>';
        messagesBox.appendChild(node);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function appendAiMessage(text) {
        const node = document.createElement('div');
        node.className = 'medical-ai-msg ai';
        node.innerHTML = '<div class="bubble">' + escHtml(text) + '</div>';
        messagesBox.appendChild(node);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function fetchThreads(openLatest) {
        return post({ mode: 'history_list' }).then(function (data) {
            threads = Array.isArray(data.threads) ? data.threads : [];
            if (openLatest && threads.length) {
                currentThreadId = threads[0].id;
            }
            renderHistory();
            if (currentThreadId) {
                openThread(currentThreadId);
            } else if (!threads.length) {
                createThread();
            }
        });
    }

    function createThread() {
        return post({ mode: 'history_create', title: 'New Chat' }).then(function (data) {
            const thread = data.thread || {};
            currentThreadId = thread.id || '';
            threads = Array.isArray(data.threads) ? data.threads : [];
            renderHistory();
            renderMessages(thread);
            input.focus();
        });
    }

    function openThread(threadId) {
        if (!threadId) return;
        currentThreadId = threadId;
        renderHistory();
        post({ mode: 'history_get', thread_id: threadId }).then(function (data) {
            if (data.error) {
                appendAiMessage(data.error);
                return;
            }
            renderMessages(data.thread || { messages: [] });
        });
    }

    function deleteCurrentThread() {
        if (!currentThreadId) return;
        post({ mode: 'history_delete', thread_id: currentThreadId }).then(function (data) {
            threads = Array.isArray(data.threads) ? data.threads : [];
            currentThreadId = threads.length ? threads[0].id : '';
            renderHistory();
            if (currentThreadId) openThread(currentThreadId);
            else createThread();
        });
    }

    function sendMessage() {
        if (loading) return;
        const text = (input.value || '').trim();
        if (!text) return;
        if (!currentThreadId) return;

        loading = true;
        sendBtn.disabled = true;
        appendUserMessage(text);
        input.value = '';
        showTyping();

        post({
            mode: 'chat',
            message: text,
            thread_id: currentThreadId,
            persist_history: '1'
        }).then(function (data) {
            hideTyping();
            if (data.error) {
                appendAiMessage(data.error);
                return;
            }
            if (data.thread_id) {
                currentThreadId = data.thread_id;
            }
            appendAiMessage(data.reply || 'No response.');
            fetchThreads(false);
        }).catch(function () {
            hideTyping();
            appendAiMessage('Connection error. Please try again.');
        }).finally(function () {
            loading = false;
            sendBtn.disabled = false;
        });
    }

    historyList.addEventListener('click', function (e) {
        const btn = e.target.closest('.medical-ai-thread');
        if (!btn) return;
        openThread(btn.getAttribute('data-id') || '');
    });

    if (suggestions) {
        suggestions.addEventListener('click', function (e) {
            const btn = e.target.closest('.medical-ai-suggest');
            if (!btn) return;
            input.value = btn.textContent || '';
            input.focus();
        });
    }

    newChatBtn.addEventListener('click', createThread);
    deleteChatBtn.addEventListener('click', deleteCurrentThread);
    searchInput.addEventListener('input', renderHistory);
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    fetchThreads(true);
})();
</script>
