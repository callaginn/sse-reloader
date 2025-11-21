class ChatApp {
	state = {
		id: sessionStorage.getItem('tabId') || `tab_${Date.now()}_${Math.random().toString(36).slice(2)}`,
		name: sessionStorage.getItem('tabName') || prompt('Name:', 'Anonymous') || 'Anonymous',
		users: new Set(),
		reconnects: 0,
		messages: [],
		messageMap: new Map()
	};

	dom = {};
	autoScroll = true;
	eventHandlers = {
		history: ({ messages }) => this.replaceHistory(messages || []),
		refresh: ({ messages }) => this.replaceHistory(messages || []),
		newMessage: ({ message }) => this.addMessage(message),
		connected: () => this.setStatus('connected')
	};

	constructor() {
		sessionStorage.setItem('tabId', this.state.id);
		sessionStorage.setItem('tabName', this.state.name);

		this.initDom();
		this.bindDomEvents();
		this.updateNameDisplay();
		this.connect();
	}

	initDom() {
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
	}

	bindDomEvents() {
		if (window.bootstrap) new bootstrap.Tooltip(this.dom.status);
		this.dom.input?.focus();

		this.dom.form?.addEventListener('submit', (event) => {
			event.preventDefault();
			this.send();
		});

		this.dom.input?.addEventListener('keypress', (event) => {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				this.send();
			}
		});

		this.dom.badge?.addEventListener('click', () => this.changeName());

		this.dom.chat?.addEventListener('click', (event) => {
			const alert = event.target.closest('.alert');
			if (alert) {
				alert.nextElementSibling?.classList.toggle('d-none');
			}
		});

		this.dom.chat?.addEventListener('scroll', () => this.updateAutoScrollFlag());
	}

	connect() {
		if (this.sse) this.sse.close();
		this.sse = new EventSource(`sse-server.php?tabId=${encodeURIComponent(this.state.id)}`);

		this.sse.onopen = () => {
			this.setStatus('connected');
			this.state.reconnects = 0;
			this.api({ d: '__USER_JOINED__', presence: 'true' }).catch(() => {});
		};

		this.sse.onmessage = ({ data }) => this.handleServerEvent(data);

		this.sse.onerror = () => {
			this.setStatus(++this.state.reconnects >= 5 ? 'error' : 'warning');
			if (this.state.reconnects >= 5) this.sse.close();
		};
	}

	handleServerEvent(raw) {
		try {
			const payload = JSON.parse(raw);
			const handler = this.eventHandlers[payload.type];
			console.log('[SSE update]', payload.type, payload);
			if (handler) handler(payload);
		} catch (err) {
			console.error('Invalid SSE payload', err, raw);
		}
	}

	async api(params) {
		const qs = new URLSearchParams({ tabId: this.state.id, senderName: this.state.name, ...params });
		const response = await fetch(`receiver.php?${qs}`);
		const data = await response.json().catch(() => ({ success: response.ok }));
		if (!response.ok || data.success === false) {
			throw new Error(data.error || 'Request failed');
		}
		return data;
	}

	async send() {
		const value = this.dom.input?.value.trim();
		if (!value) return;

		this.dom.btn.disabled = true;
		try {
			const { message } = await this.api({ d: value });
			this.dom.input.value = '';
			this.dom.input.focus();
			if (message) {
				this.addMessage(message, { preferScroll: true });
			}
		} catch (err) {
			alert('Send failed');
		} finally {
			this.dom.btn.disabled = false;
		}
	}

	replaceHistory(messages = []) {
		if (!this.dom.chat) return;
		const shouldStick = this.autoScroll || this.isAtBottom();
		const frag = document.createDocumentFragment();
		const users = new Set();

		this.state.messages = [];
		this.state.messageMap.clear();

		messages.forEach((message) => {
			this.state.messages.push(message);
			const node = this.renderMessage(message);
			this.state.messageMap.set(message.id, node);
			frag.appendChild(node);
			this.trackSender(message, users);
		});

		this.dom.chat.replaceChildren(frag);
		this.state.users = users;
		this.refreshUserList();

		if (shouldStick) {
			this.autoScroll = true;
			this.deferScroll();
		} else {
			this.autoScroll = false;
		}
	}

	addMessage(message, { preferScroll = false } = {}) {
		if (!message || this.state.messageMap.has(message.id) || !this.dom.chat) {
			return;
		}

		const shouldScroll = preferScroll || this.autoScroll || this.isAtBottom();
		this.state.messages.push(message);
		const node = this.renderMessage(message);
		this.state.messageMap.set(message.id, node);
		this.dom.chat.appendChild(node);
		this.trackSender(message);

		if (shouldScroll) {
			this.autoScroll = true;
			this.deferScroll();
		}
	}

	async reload() {
		const response = await fetch(`data/messages.json?t=${Date.now()}`);
		if (!response.ok) return;
		const messages = await response.json();
		this.replaceHistory(messages);
	}

	renderMessage(message) {
		const isMine = message.senderName === this.state.name;
		const wrapper = this.createEl('div', `d-flex flex-column gap-1 ${isMine ? 'align-items-end' : 'align-items-start'}`);
		wrapper.appendChild(this.createEl('div', 'text-secondary small', message.senderName));

		const bubble = this.createEl('div', `alert ${isMine ? 'alert-primary' : 'alert-secondary'} cursor-pointer`);
		bubble.appendChild(this.createEl('div', '', message.content));
		wrapper.appendChild(bubble);

		wrapper.appendChild(this.createEl('div', 'text-muted d-none small', new Date(message.timestamp * 1000).toLocaleTimeString()));
		return wrapper;
	}

	createEl(tag, cls, text = '') {
		const node = document.createElement(tag);
		if (cls) node.className = cls;
		if (text) node.textContent = text;
		return node;
	}

	trackSender(message, bucket = this.state.users) {
		const sender = message?.senderName;
		if (sender && sender !== this.state.name) {
			bucket.add(sender);
			if (bucket === this.state.users) {
				this.refreshUserList();
			}
		}
	}

	isAtBottom(padding = 60) {
		if (!this.dom.chat) return true;
		const { scrollHeight, scrollTop, clientHeight } = this.dom.chat;
		return scrollHeight - scrollTop - clientHeight < padding;
	}

	deferScroll() {
		requestAnimationFrame(() => this.scrollToBottom());
	}

	scrollToBottom() {
		if (this.dom.chat) {
			this.dom.chat.scrollTop = this.dom.chat.scrollHeight;
		}
		this.autoScroll = true;
	}

	updateAutoScrollFlag() {
		this.autoScroll = this.isAtBottom();
	}

	async changeName() {
		const current = this.state.name;
		const updated = prompt('New Name:', current);
		if (!updated || !updated.trim() || updated === current) return;

		this.state.name = updated.trim();
		sessionStorage.setItem('tabName', this.state.name);
		this.updateNameDisplay();

		try {
			await this.api({ d: '__NAME_CHANGE__', oldName: current, nameChange: 'true' });
		} catch (err) {
			console.error('Name change failed', err);
		}
	}

	refreshUserList() {
		if (!this.dom.users) return;
		const list = [...this.state.users].sort();
		this.dom.users.innerHTML = list.length ? `<i class="fas fa-users"></i> ${list.join(', ')}` : '';
	}

	updateNameDisplay() {
		if (this.dom.name) this.dom.name.textContent = this.state.name;
		document.title = `SSE Messenger - ${this.state.name}`;
	}

	setStatus(type) {
		if (!this.dom.status) return;
		this.dom.status.className = `status-icon ${type}`;
		const titles = { connected: 'Connected', warning: 'Reconnecting...', error: 'Failed' };
		this.dom.status.title = titles[type] || '';
		const tipApi = window.bootstrap?.Tooltip;
		const tooltip = tipApi ? tipApi.getInstance(this.dom.status) : null;
		if (tooltip) tooltip.setContent({ '.tooltip-inner': this.dom.status.title });
	}
}

window.addEventListener('DOMContentLoaded', () => {
	window.app = new ChatApp();
});
