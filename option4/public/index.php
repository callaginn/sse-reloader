<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SSE Messenger</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="styles.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        
        <script>
            // Virtual DOM with Incremental Updates
            // Only updates what changed instead of full reload
            class ChatApp {
                state = {
                    id: sessionStorage.getItem('tabId') || `tab_${Date.now()}_${Math.random().toString(36).slice(2)}`,
                    name: sessionStorage.getItem('tabName') || prompt("Name:", "Anonymous") || "Anonymous",
                    users: new Set(),
                    reconnects: 0,
                    messages: [], // Cache messages locally
                    messageMap: new Map() // Track rendered messages by ID
                };
                
                dom = {};

                constructor() {
                    sessionStorage.setItem('tabId', this.state.id);
                    sessionStorage.setItem('tabName', this.state.name);

                    const $ = (sel) => document.querySelector(sel);
                    this.dom = {
                        chat: $('#chatContainer'), 
                        input: $('#inp'), 
                        btn: $('#sendBtn'),
                        status: $('#statusIcon'), 
                        users: $('#activeUsers'), 
                        name: $('#clientName'),
                        badge: $('.badge'), 
                        form: $('form')
                    };

                    this.init();
                }

                init() {
                    this.updateNameDisplay();
                    this.connect();
                    if (window.bootstrap) new bootstrap.Tooltip(this.dom.status);
                    this.dom.input?.focus();

                    this.dom.form?.addEventListener('submit', (e) => { e.preventDefault(); this.send(); });
                    this.dom.input?.addEventListener('keypress', (e) => (e.key === 'Enter' && !e.shiftKey) && (e.preventDefault(), this.send()));
                    this.dom.badge?.addEventListener('click', () => this.changeName());
                    
                    this.dom.chat?.addEventListener('click', e => {
                        const alert = e.target.closest('.alert');
                        if (alert) alert.nextElementSibling?.classList.toggle('d-none');
                    });
                }

                el(tag, cls, text = '') {
                    const el = document.createElement(tag);
                    if (cls) el.className = cls;
                    if (text) el.textContent = text;
                    return el;
                }

                connect() {
                    if (this.sse) this.sse.close();
                    this.sse = new EventSource(`sse-server.php?tabId=${encodeURIComponent(this.state.id)}`);

                    this.sse.onopen = () => {
                        this.setStatus('connected');
                        this.state.reconnects = 0;
                        this.api({ d: '__USER_JOINED__', presence: 'true' });
                    };

                    this.sse.onmessage = (e) => {
                        try {
                            const data = JSON.parse(e.data);
                            if (data.type === 'history' || data.type === 'refresh') this.loadHistory(data.messages);
                            else if (data.type === 'newMessage') this.addMessage(data.message);
                        } catch (err) {
                            console.error(err);
                        }
                    };

                    this.sse.onerror = () => {
                        this.setStatus(++this.state.reconnects >= 5 ? 'error' : 'warning');
                        if (this.state.reconnects >= 5) this.sse.close();
                    };
                }

                async api(params) {
                    const qs = new URLSearchParams({ tabId: this.state.id, senderName: this.state.name, ...params });
                    const res = await fetch(`receiver.php?${qs}`);
                    const data = await res.json().catch(() => ({ success: res.ok }));
                    if (!res.ok || data.success === false) throw new Error(data.error);
                    return data;
                }

                async send() {
                    const val = this.dom.input.value.trim();
                    if (!val) return;
                    
                    this.dom.btn.disabled = true;
                    try {
                        await this.api({ d: val });
                        this.dom.input.value = "";
                        this.dom.input.focus();
                    } catch (e) {
                        alert("Send failed");
                    } finally {
                        this.dom.btn.disabled = false;
                    }
                }

                // Load initial message history
                loadHistory(messages) {
                    this.state.messages = messages;
                    this.state.messageMap.clear();
                    this.state.users.clear();
                    
                    const wasAtBottom = this.isAtBottom();
                    const frag = document.createDocumentFragment();
                    
                    messages.forEach(msg => {
                        const el = this.renderMessage(msg);
                        this.state.messageMap.set(msg.id, el);
                        frag.appendChild(el);
                        
                        const isMe = msg.senderName === this.state.name;
                        if (!isMe && msg.senderName) this.state.users.add(msg.senderName);
                    });
                    
                    this.dom.chat.replaceChildren(frag);
                    this.refreshUserList();
                    
                    if (wasAtBottom) requestAnimationFrame(() => this.scrollToBottom());
                }

                // Add single new message (incremental update)
                addMessage(msg) {
                    if (this.state.messageMap.has(msg.id)) return; // Already exists
                    
                    const wasAtBottom = this.isAtBottom();
                    
                    this.state.messages.push(msg);
                    const el = this.renderMessage(msg);
                    this.state.messageMap.set(msg.id, el);
                    this.dom.chat.appendChild(el);
                    
                    const isMe = msg.senderName === this.state.name;
                    if (!isMe && msg.senderName) {
                        this.state.users.add(msg.senderName);
                        this.refreshUserList();
                    }
                    
                    if (wasAtBottom) requestAnimationFrame(() => this.scrollToBottom());
                }

                // Full reload with diff-based update
                async reload() {
                    const res = await fetch(`messages.json?t=${Date.now()}`);
                    if (!res.ok) return;
                    
                    const newMessages = await res.json();
                    const wasAtBottom = this.isAtBottom();
                    
                    // Build new message ID set
                    const newIds = new Set(newMessages.map(m => m.id));
                    const existingIds = new Set(this.state.messageMap.keys());
                    
                    // Remove messages that no longer exist
                    for (const id of existingIds) {
                        if (!newIds.has(id)) {
                            this.state.messageMap.get(id)?.remove();
                            this.state.messageMap.delete(id);
                        }
                    }
                    
                    // Update or add messages
                    this.state.users.clear();
                    const frag = document.createDocumentFragment();
                    
                    newMessages.forEach((msg, idx) => {
                        const isMe = msg.senderName === this.state.name;
                        if (!isMe && msg.senderName) this.state.users.add(msg.senderName);
                        
                        if (!this.state.messageMap.has(msg.id)) {
                            // New message
                            const el = this.renderMessage(msg);
                            this.state.messageMap.set(msg.id, el);
                            frag.appendChild(el);
                        } else {
                            // Update existing if needed (e.g., name change)
                            const existing = this.state.messageMap.get(msg.id);
                            const oldMsg = this.state.messages.find(m => m.id === msg.id);
                            if (oldMsg && oldMsg.senderName !== msg.senderName) {
                                const newEl = this.renderMessage(msg);
                                existing.replaceWith(newEl);
                                this.state.messageMap.set(msg.id, newEl);
                            }
                        }
                    });
                    
                    if (frag.children.length > 0) {
                        this.dom.chat.appendChild(frag);
                    }
                    
                    this.state.messages = newMessages;
                    this.refreshUserList();
                    
                    if (wasAtBottom) requestAnimationFrame(() => this.scrollToBottom());
                }

                renderMessage(msg) {
                    const isMe = msg.senderName === this.state.name;
                    const wrap = this.el('div', `d-flex flex-column gap-1 ${isMe ? 'align-items-end' : 'align-items-start'}`);
                    wrap.appendChild(this.el('div', 'text-secondary small', msg.senderName));
                    
                    const bubble = this.el('div', `alert ${isMe ? 'alert-primary' : 'alert-secondary'} cursor-pointer`);
                    bubble.appendChild(this.el('div', '', msg.content));
                    wrap.appendChild(bubble);
                    
                    wrap.appendChild(this.el('div', 'text-muted d-none small', new Date(msg.timestamp * 1000).toLocaleTimeString()));
                    
                    return wrap;
                }

                isAtBottom() {
                    const { scrollHeight, scrollTop, clientHeight } = this.dom.chat;
                    return (scrollHeight - scrollTop - clientHeight) < 100;
                }

                scrollToBottom() {
                    this.dom.chat.scrollTop = this.dom.chat.scrollHeight;
                }

                async changeName() {
                    const old = this.state.name;
                    const newName = prompt("New Name:", old);
                    if (!newName || !newName.trim() || newName === old) return;

                    this.state.name = newName.trim();
                    sessionStorage.setItem('tabName', this.state.name);
                    this.updateNameDisplay();

                    try {
                        await this.api({ d: '__NAME_CHANGE__', oldName: old, nameChange: 'true' });
                    } catch (err) {
                        console.error('Name change failed:', err);
                    }
                }

                refreshUserList() {
                    const list = [...this.state.users].sort();
                    this.dom.users.innerHTML = list.length ? `<i class="fas fa-users"></i> ${list.join(', ')}` : '';
                }

                updateNameDisplay() {
                    this.dom.name.textContent = this.state.name;
                    document.title = `SSE Messenger - ${this.state.name}`;
                }

                setStatus(type) {
                    this.dom.status.className = `status-icon ${type}`;
                    const titles = { connected: 'Connected', warning: 'Reconnecting...', error: 'Failed' };
                    this.dom.status.title = titles[type];
                    
                    const tooltip = bootstrap.Tooltip.getInstance(this.dom.status);
                    if (tooltip) tooltip.setContent({ '.tooltip-inner': titles[type] });
                }
            }

            document.addEventListener('DOMContentLoaded', () => window.app = new ChatApp());
        </script>
    </head>
    
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <div class="status-bar-left">
                        9:41
                    </div>
                    <div class="status-bar-right">
                        <div id="statusIcon" class="status-icon connected" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Connected"></div>
                        <i class="fas fa-signal"></i>
                        <i class="fas fa-battery-full"></i>
                    </div>
                </div>
                
                <div class="bg-primary text-white" id="chatHeader">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="h4"><i class="fas fa-comments"></i> Messenger</h4>
                        <span class="badge" title="Click to change name">
                            <span id="clientName">You</span>
                        </span>
                    </div>
                    <div class="active-users" id="activeUsers"></div>
                </div>
                
                <div class="card-body" id="chatContainer"></div>
                
                <div class="card-footer" id="chatInput">
                    <form>
                        <div class="d-flex gap-2">
                            <textarea 
                                id="inp" 
                                class="form-control" 
                                rows="1" 
                                placeholder="Type a message..."></textarea>
                            <button 
                                id="sendBtn"
                                type="submit" 
                                class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    </body>
</html>
