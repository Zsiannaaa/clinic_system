<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Access denied.');
}
?>

        </div><!-- /content-inner -->
    </main><!-- /page-content -->

    <!-- Footer bar at the bottom of every page -->
    <footer class="page-footer">
        <span>&copy; <?= date('Y') ?> Cryptalis Clinic</span>
        <span>Cryptalis Clinic System</span>
    </footer>

</div><!-- /main-wrapper -->

<!-- Bootstrap JS (for dropdowns, modals, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Replace [data-lucide] placeholders with Bootstrap Icons.
    function renderAppIcons() {
        const iconMap = {
            'alert-circle': 'exclamation-circle',
            'alert-triangle': 'exclamation-triangle',
            'bot': 'robot',
            'calendar': 'calendar-event',
            'calendar-check': 'calendar-check',
            'calendar-clock': 'calendar2-week',
            'calendar-plus': 'calendar-plus',
            'calendar-x2': 'calendar-x',
            'check-circle': 'check-circle',
            'chevron-down': 'chevron-down',
            'clock': 'clock',
            'eye': 'eye',
            'eye-off': 'eye-slash',
            'file-text': 'file-earmark-text',
            'home': 'house',
            'info': 'info-circle',
            'layout-dashboard': 'grid-1x2',
            'log-in': 'box-arrow-in-right',
            'log-out': 'box-arrow-right',
            'menu': 'list',
            'monitor': 'display',
            'pencil': 'pencil',
            'paperclip': 'paperclip',
            'plus': 'plus',
            'plus-circle': 'plus-circle',
            'printer': 'printer',
            'save': 'floppy',
            'search': 'search',
            'shield': 'shield-check',
            'shield-off': 'shield-x',
            'sparkles': 'stars',
            'stethoscope': 'heart-pulse',
            'sun': 'sun',
            'trash-2': 'trash',
            'user': 'person',
            'user-check': 'person-check',
            'user-plus': 'person-plus',
            'users': 'people',
            'x': 'x-lg',
            'x-circle': 'x-circle',
            'zap': 'lightning-charge'
        };

        document.querySelectorAll('[data-lucide]').forEach(function (node) {
            const name = node.getAttribute('data-lucide');
            const mapped = iconMap[name] || 'circle';
            const icon = document.createElement('i');
            icon.className = 'bi bi-' + mapped + ' ' + (node.getAttribute('class') || '');
            icon.style.cssText = node.getAttribute('style') || '';
            icon.setAttribute('aria-hidden', 'true');
            node.replaceWith(icon);
        });
    }

    renderAppIcons();

    // Sidebar toggle — collapses the sidebar on desktop, slides it in on mobile
    function toggleSidebar() {
        const sidebar  = document.getElementById('sidebar');
        const wrapper  = document.getElementById('mainWrapper');
        const overlay  = document.getElementById('sidebarOverlay');
        if (window.innerWidth < 992) {
            // Mobile: slide the sidebar in from the left
            sidebar.classList.toggle('sidebar-open');
            overlay.classList.toggle('active');
        } else {
            // Desktop: collapse the sidebar to icon-only mode
            sidebar.classList.toggle('sidebar-collapsed');
            wrapper.classList.toggle('sidebar-collapsed');
        }
    }

    // Close sidebar when the overlay (dark background) is clicked on mobile
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('sidebar-open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }

    // Convert top flash alerts into closable popup cards.
    function initFlashPopups() {
        const host = document.querySelector('.content-inner');
        if (!host) return;

        const flashAlerts = host.querySelectorAll(':scope > .alert.alert-success, :scope > .alert.alert-danger');
        if (!flashAlerts.length) return;

        const stack = document.createElement('div');
        stack.className = 'flash-popup-stack';
        document.body.appendChild(stack);

        flashAlerts.forEach(function (alertEl) {
            alertEl.classList.add('flash-popup');
            alertEl.classList.remove('mb-4');

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn btn-sm btn-outline-danger flash-close';
            closeBtn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i> Close';
            closeBtn.setAttribute('aria-label', 'Close message');
            closeBtn.addEventListener('click', function () {
                alertEl.classList.add('is-closing');
                window.setTimeout(function () {
                    alertEl.remove();
                    if (!stack.children.length) {
                        stack.remove();
                    }
                }, 180);
            });

            alertEl.appendChild(closeBtn);
            stack.appendChild(alertEl);
        });
    }

    initFlashPopups();
</script>

<!-- ══ Custom Delete Confirmation Modal ══════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border-radius:14px;border:1px solid var(--card-border);box-shadow:0 8px 32px rgba(0,0,0,0.13)">
      <div class="modal-body" style="padding:32px 28px 20px;text-align:center">
        <div style="width:56px;height:56px;background:var(--danger-pale);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
               stroke="var(--danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6l-1 14H6L5 6"></path>
            <path d="M10 11v6"></path><path d="M14 11v6"></path>
            <path d="M9 6V4h6v2"></path>
          </svg>
        </div>
        <h5 id="deleteModalTitle" style="font-size:1.05rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Confirm Delete</h5>
        <p id="deleteModalMessage" style="font-size:.875rem;color:var(--text-secondary);margin-bottom:0">Are you sure you want to delete this record? This action cannot be undone.</p>
      </div>
      <div class="modal-footer" style="border:none;padding:0 28px 24px;justify-content:center;gap:10px">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="deleteModalConfirm" class="btn btn-outline-danger">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6l-1 14H6L5 6"></path>
          </svg>
          Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    // One shared modal handles both delete forms and confirm actions.
    const modal      = document.getElementById('deleteModal');
    const message    = document.getElementById('deleteModalMessage');
    const confirmBtn = document.getElementById('deleteModalConfirm');
    const modalTitle = document.getElementById('deleteModalTitle');
    if (!modal) return;

    const bsModal = new bootstrap.Modal(modal);
    let targetForm = null;

    // js-delete-btn — red confirm for destructive deletes
    document.querySelectorAll('.js-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            targetForm = btn.closest('form');
            message.textContent = btn.dataset.message || 'Are you sure? This action cannot be undone.';
            modalTitle.textContent = 'Confirm Delete';
            confirmBtn.className = 'btn btn-outline-danger';
            confirmBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14H6L5 6"></path></svg> Yes, Delete';
            bsModal.show();
        });
    });

    // js-confirm-btn — green confirm for non-destructive actions (e.g. mark as done)
    document.querySelectorAll('.js-confirm-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            targetForm = btn.closest('form');
            message.textContent = btn.dataset.message || 'Are you sure?';
            modalTitle.textContent = 'Confirm Action';
            confirmBtn.className = 'btn btn-outline-success';
            confirmBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 13 4 10"></polyline></svg> Yes, Confirm';
            bsModal.show();
        });
    });

    confirmBtn.addEventListener('click', function () {
        if (targetForm) targetForm.submit();
    });
})();
</script>































<!-- ══ Floating AI Medical Assistant ══════════════════════════ -->
<div id="aiChatWidget" style="position:fixed;bottom:88px;right:24px;z-index:9999;font-family:'Inter',-apple-system,sans-serif">

    <!-- Toggle button -->
    <button id="aiChatToggle" onclick="toggleAiChat()"
            style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border:none;cursor:pointer;box-shadow:0 4px 16px rgba(192,57,43,.35);display:flex;align-items:center;justify-content:center;color:#fff;transition:transform .2s"
            title="AI Medical Assistant">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2a4 4 0 0 1 4 4c0 1.5-.8 2.8-2 3.5V11h1a2 2 0 0 1 2 2v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v-1H6a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1v-1a2 2 0 0 1 2-2h1V9.5C8.8 8.8 8 7.5 8 6a4 4 0 0 1 4-4z"/>
            <circle cx="9" cy="14" r=".5" fill="currentColor"/>
            <circle cx="15" cy="14" r=".5" fill="currentColor"/>
        </svg>
    </button>

    <!-- Chat panel -->
    <div id="aiChatPanel"
         style="position:absolute;bottom:64px;right:0;width:360px;background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden;border:1px solid #e2e8f0;flex-direction:column">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));padding:14px 16px;display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4c0 1.5-.8 2.8-2 3.5V11h1a2 2 0 0 1 2 2v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v-1H6a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1v-1a2 2 0 0 1 2-2h1V9.5C8.8 8.8 8 7.5 8 6a4 4 0 0 1 4-4z"/><circle cx="9" cy="14" r=".5" fill="#fff"/><circle cx="15" cy="14" r=".5" fill="#fff"/></svg>
            </div>
            <div style="flex:1">
                <div style="color:#fff;font-weight:700;font-size:.9rem">Medical AI Assistant</div>
                <div style="color:rgba(255,255,255,.8);font-size:.72rem">Powered by AI &bull; Cryptalis Clinic</div>
            </div>
            <button onclick="toggleAiChat()" style="background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:18px;line-height:1;padding:0">&times;</button>
        </div>

        <!-- Messages -->
        <div id="aiMessages"
             style="height:320px;overflow-y:auto;padding:14px 14px 8px;display:flex;flex-direction:column;gap:10px;background:#fafafa">
            <div class="ai-msg ai" style="align-self:flex-start;max-width:88%">
                <div style="background:var(--primary-pale);border:1px solid var(--primary-light);color:var(--text-primary);padding:10px 13px;border-radius:0 10px 10px 10px;font-size:.82rem;line-height:1.55">
                    Hello! I&rsquo;m your medical assistant. Ask me anything &mdash; symptoms, medications, procedures, or clinical guidance.
                    <div style="margin-top:6px;font-size:.7rem;color:var(--primary);font-style:italic">AI reference only &mdash; not a substitute for clinical judgment.</div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div style="padding:12px;border-top:1px solid #e2e8f0;background:#fff;display:flex;gap:8px;align-items:flex-end">
            <textarea id="aiChatInput" rows="2" placeholder="Ask a medical question..."
                      style="flex:1;resize:none;border:1.5px solid var(--card-border);border-radius:8px;padding:8px 10px;font-size:.82rem;font-family:inherit;outline:none;line-height:1.4"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAiMessage();}"></textarea>
            <button onclick="sendAiMessage()" id="aiSendBtn"
                    style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));border:none;border-radius:8px;padding:9px 13px;cursor:pointer;color:#fff;flex-shrink:0">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
        <div style="padding:4px 14px 10px;font-size:.68rem;color:#a0aec0;text-align:center">AI suggestions only &mdash; always verify with clinical guidelines.</div>
    </div>
</div>

<style>
.ai-msg.user > div { background:var(--primary-dark);color:#fff;border-radius:10px 0 10px 10px;margin-left:auto; }
.ai-msg.ai   > div { background:var(--primary-pale);border:1px solid var(--primary-light);color:var(--text-primary);border-radius:0 10px 10px 10px; }
.ai-typing { display:flex;gap:4px;align-items:center;padding:10px 13px }
.ai-typing span { width:7px;height:7px;background:var(--primary);border-radius:50%;animation:aiDot 1.2s infinite }
.ai-typing span:nth-child(2){animation-delay:.2s}
.ai-typing span:nth-child(3){animation-delay:.4s}
@keyframes aiDot{0%,80%,100%{transform:scale(.7);opacity:.5}40%{transform:scale(1);opacity:1}}
#aiChatPanel { display:none }
#aiChatPanel.open { display:flex;flex-direction:column }
@media (max-width: 768px){
  #aiChatWidget { bottom:74px !important; right:14px !important; }
  #aiChatPanel  { width:min(92vw,360px) !important; }
}
</style>

<script>
var aiHistory = [];
var isMedicalAiPage = window.location.pathname.endsWith('/medical_ai.php');

if (isMedicalAiPage) {
    var widget = document.getElementById('aiChatWidget');
    if (widget) widget.style.display = 'none';
}

function toggleAiChat() {
    if (isMedicalAiPage) return;
    var panel = document.getElementById('aiChatPanel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        document.getElementById('aiChatInput').focus();
    }
}

function sendAiMessage() {
    if (isMedicalAiPage) return;
    var input   = document.getElementById('aiChatInput');
    var msgs    = document.getElementById('aiMessages');
    var sendBtn = document.getElementById('aiSendBtn');
    var message = input.value.trim();
    if (!message) return;

    // Append user bubble
    var userDiv = document.createElement('div');
    userDiv.className = 'ai-msg user';
    userDiv.style.cssText = 'align-self:flex-end;max-width:88%';
    userDiv.innerHTML = '<div style="padding:10px 13px;font-size:.82rem;line-height:1.55">' + escHtml(message) + '</div>';
    msgs.appendChild(userDiv);

    // Typing indicator
    var typing = document.createElement('div');
    typing.className = 'ai-msg ai';
    typing.style.cssText = 'align-self:flex-start;max-width:88%';
    typing.innerHTML = '<div class="ai-typing"><span></span><span></span><span></span></div>';
    msgs.appendChild(typing);
    msgs.scrollTop = msgs.scrollHeight;

    input.value = '';
    sendBtn.disabled = true;

    // Add to history
    aiHistory.push({role:'user', text: message});

    var form = new FormData();
    form.append('mode',    'chat');
    form.append('message', message);
    form.append('history', JSON.stringify(aiHistory.slice(0, -1))); // send history without current

    fetch('/clinic_1/modules/ai/chat.php', {method:'POST', body:form})
        .then(function(r){ return r.json(); })
        .then(function(data) {
            msgs.removeChild(typing);
            var reply = data.reply || data.error || 'No response.';
            aiHistory.push({role:'model', text: reply});

            var aiDiv = document.createElement('div');
            aiDiv.className = 'ai-msg ai';
            aiDiv.style.cssText = 'align-self:flex-start;max-width:92%';
            aiDiv.innerHTML = '<div style="padding:10px 13px;font-size:.82rem;line-height:1.6;white-space:pre-wrap">' + escHtml(reply) + '</div>';
            msgs.appendChild(aiDiv);
            msgs.scrollTop = msgs.scrollHeight;
        })
        .catch(function() {
            msgs.removeChild(typing);
            var errDiv = document.createElement('div');
            errDiv.className = 'ai-msg ai';
            errDiv.style.cssText = 'align-self:flex-start;max-width:88%';
            errDiv.innerHTML = '<div style="padding:10px 13px;font-size:.82rem;color:#e53e3e">Connection error. Please try again.</div>';
            msgs.appendChild(errDiv);
            msgs.scrollTop = msgs.scrollHeight;
        })
        .finally(function(){ sendBtn.disabled = false; });
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
