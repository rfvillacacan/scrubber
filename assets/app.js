(function () {
    "use strict";

    const rawInput = document.getElementById("rawInput");
    const scrubbedOutput = document.getElementById("scrubbedOutput");
    const llmInput = document.getElementById("llmInput");
    const restoredOutput = document.getElementById("restoredOutput");
    const scrubStats = document.getElementById("scrubStats");
    const historyContainer = document.getElementById("historyContainer");
    const quickTestStatus = document.getElementById("quickTestStatus");
    const appMain = document.getElementById("appMain");
    const loadingOverlay = document.getElementById("loadingOverlay");
    const scrubBtn = document.getElementById("scrubBtn");
    const restoreBtn = document.getElementById("restoreBtn");
    const quickTestBtn = document.getElementById("quickTestBtn");
    const clearBtn = document.getElementById("clearBtn");
    const refreshHistoryBtn = document.getElementById("refreshHistoryBtn");
    const modalOverlay = document.getElementById("modalOverlay");
    const modalTitle = document.getElementById("modalTitle");
    const modalMessage = document.getElementById("modalMessage");
    const modalOk = document.getElementById("modalOk");
    const modalCancel = document.getElementById("modalCancel");
    let modalResolve = null;
    const resumeBtn = document.getElementById("resumeBtn");
    const resumeOverlay = document.getElementById("resumeOverlay");
    const resumeSessionId = document.getElementById("resumeSessionId");
    const resumePassphrase = document.getElementById("resumePassphrase");
    const resumeError = document.getElementById("resumeError");
    const recentSessions = document.getElementById("recentSessions");
    const resumeOk = document.getElementById("resumeOk");
    const resumeCancel = document.getElementById("resumeCancel");
    const lockBtn = document.getElementById("lockBtn");
    const encryptBtn = document.getElementById("encryptBtn");
    const lockOverlay = document.getElementById("lockOverlay");
    const lockPassphrase = document.getElementById("lockPassphrase");
    const lockOk = document.getElementById("lockOk");
    const lockCancel = document.getElementById("lockCancel");
    const rulesetList = document.getElementById("rulesetList");
    const rulesetFile = document.getElementById("rulesetFile");
    const uploadRulesetBtn = document.getElementById("uploadRulesetBtn");
    const backupRulesBtn = document.getElementById("backupRulesBtn");
    const copySessionBtn = document.getElementById("copySessionBtn");
    const pasteScrubBtn = document.getElementById("pasteScrubBtn");
    const pasteRestoreBtn = document.getElementById("pasteRestoreBtn");
    const rawInputMeta = document.getElementById("rawInputMeta");
    const scrubbedOutputMeta = document.getElementById("scrubbedOutputMeta");
    const llmInputMeta = document.getElementById("llmInputMeta");
    const restoredOutputMeta = document.getElementById("restoredOutputMeta");
    const sessionStatus = document.getElementById("sessionStatus");
    const srStatus = document.getElementById("srStatus");
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const paneFeedbackMap = {
        rawInput: document.getElementById("feedbackRawInput"),
        scrubbedOutput: document.getElementById("feedbackScrubbedOutput"),
        llmInput: document.getElementById("feedbackLlmInput"),
        restoredOutput: document.getElementById("feedbackRestoredOutput")
    };
    const paneFeedbackTimers = {};
    let isGlobalLoading = false;
    let statusToast = null;
    let statusToastTimer = null;
    let activeDialogOverlay = null;
    let lastFocusedElement = null;
    const recentSessionsKey = "scrubber_recent_sessions";

    function setLoading(isLoading) {
        if (!loadingOverlay) {
            return;
        }
        isGlobalLoading = Boolean(isLoading);
        if (appMain) {
            appMain.setAttribute("aria-busy", isLoading ? "true" : "false");
        }
        loadingOverlay.classList.toggle("hidden", !isLoading);
        loadingOverlay.setAttribute("aria-hidden", isLoading ? "false" : "true");
        const disabled = Boolean(isLoading);
        if (scrubBtn) scrubBtn.disabled = disabled;
        if (restoreBtn) restoreBtn.disabled = disabled;
        if (quickTestBtn) {
            quickTestBtn.disabled = disabled;
        }
        if (clearBtn) clearBtn.disabled = disabled;
        if (refreshHistoryBtn) refreshHistoryBtn.disabled = disabled;
        document.querySelectorAll(".pane-actions button").forEach((btn) => {
            btn.disabled = disabled;
        });
        if (disabled) {
            announceForA11y("Processing request.");
        }
        if (!disabled) {
            updateQuickTestEnabled();
            announceForA11y("Request completed.");
        }
    }

    function updateTextareaMeta(el, metaEl) {
        if (!el || !metaEl) {
            return;
        }
        const text = el.value || "";
        const lines = text.length === 0 ? 0 : text.split(/\r?\n/).length;
        metaEl.textContent = `Chars: ${text.length} | Lines: ${lines}`;
    }

    function refreshAllTextareaMeta() {
        updateTextareaMeta(rawInput, rawInputMeta);
        updateTextareaMeta(scrubbedOutput, scrubbedOutputMeta);
        updateTextareaMeta(llmInput, llmInputMeta);
        updateTextareaMeta(restoredOutput, restoredOutputMeta);
    }

    function announceForA11y(message) {
        if (!srStatus || !message) {
            return;
        }
        srStatus.textContent = "";
        setTimeout(() => {
            srStatus.textContent = message;
        }, 10);
    }

    function getFocusableElements(container) {
        if (!container) {
            return [];
        }
        return Array.from(container.querySelectorAll(
            "a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex='-1'])"
        ));
    }

    function openDialog(overlay, focusEl) {
        if (!overlay) {
            return;
        }
        lastFocusedElement = document.activeElement;
        activeDialogOverlay = overlay;
        overlay.classList.remove("hidden");
        overlay.setAttribute("aria-hidden", "false");
        (focusEl || getFocusableElements(overlay)[0] || overlay).focus();
    }

    function closeDialog(overlay) {
        if (!overlay) {
            return;
        }
        overlay.classList.add("hidden");
        overlay.setAttribute("aria-hidden", "true");
        if (activeDialogOverlay === overlay) {
            activeDialogOverlay = null;
        }
        if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
            lastFocusedElement.focus();
        }
    }

    function hasRestoredOutput() {
        return Boolean(restoredOutput && restoredOutput.value.trim().length > 0);
    }

    function updateQuickTestEnabled() {
        if (!quickTestBtn) {
            return;
        }
        quickTestBtn.disabled = isGlobalLoading || !hasRestoredOutput();
    }

    function showPaneFeedback(targetId, message, tone = "success") {
        const el = paneFeedbackMap[targetId];
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.remove("success", "warn", "error");
        el.classList.add(tone);
        if (paneFeedbackTimers[targetId]) {
            clearTimeout(paneFeedbackTimers[targetId]);
        }
        paneFeedbackTimers[targetId] = setTimeout(() => {
            el.textContent = "";
            el.classList.remove("success", "warn", "error");
        }, 2200);
        announceForA11y(message);
    }

    async function runWithButtonBusy(btn, busyText, fn) {
        if (!btn) {
            return fn();
        }
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.classList.add("is-busy");
        if (busyText) {
            btn.textContent = busyText;
        }
        try {
            return await fn();
        } finally {
            btn.classList.remove("is-busy");
            btn.textContent = originalText;
            btn.disabled = false;
            updateQuickTestEnabled();
        }
    }

    function showStatusToast(message, tone = "info") {
        if (!statusToast) {
            statusToast = document.createElement("div");
            statusToast.className = "status-toast hidden";
            statusToast.setAttribute("role", "status");
            statusToast.setAttribute("aria-live", "polite");
            document.body.appendChild(statusToast);
        }

        statusToast.textContent = message;
        statusToast.classList.remove("hidden", "success", "warn", "error");
        statusToast.classList.add(tone);
        announceForA11y(message);

        if (statusToastTimer) {
            clearTimeout(statusToastTimer);
        }
        statusToastTimer = setTimeout(() => {
            if (statusToast) {
                statusToast.classList.add("hidden");
            }
        }, 1800);
    }

    function readRecentSessions() {
        try {
            const raw = localStorage.getItem(recentSessionsKey);
            if (!raw) {
                return [];
            }
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed.filter((id) => typeof id === "string" && /^[a-f0-9]{32}$/.test(id));
        } catch (err) {
            return [];
        }
    }

    function writeRecentSessions(ids) {
        try {
            localStorage.setItem(recentSessionsKey, JSON.stringify(ids.slice(0, 6)));
        } catch (err) {
        }
    }

    function rememberSessionId(sessionId) {
        if (!/^[a-f0-9]{32}$/.test(sessionId)) {
            return;
        }
        const ids = readRecentSessions();
        const next = [sessionId, ...ids.filter((id) => id !== sessionId)].slice(0, 6);
        writeRecentSessions(next);
    }

    function renderRecentSessions() {
        if (!recentSessions) {
            return;
        }
        recentSessions.innerHTML = "";
        const ids = readRecentSessions();
        if (ids.length === 0) {
            return;
        }
        const label = document.createElement("small");
        label.className = "modal-hint";
        label.textContent = "Recent session IDs";
        recentSessions.appendChild(label);
        ids.forEach((id) => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "recent-session-chip";
            btn.textContent = `${id.slice(0, 8)}...${id.slice(-4)}`;
            btn.title = id;
            btn.addEventListener("click", () => {
                if (resumeSessionId) {
                    resumeSessionId.value = id;
                    resumeSessionId.focus();
                }
            });
            recentSessions.appendChild(btn);
        });
    }

    function showModal({ title, message, confirm, preformatted }) {
        if (!modalOverlay) {
            return Promise.resolve(false);
        }
        modalTitle.textContent = title || "Confirm";
        modalMessage.innerHTML = "";
        if (preformatted) {
            const pre = document.createElement("pre");
            pre.className = "modal-pre";
            pre.textContent = message || "";
            modalMessage.appendChild(pre);
        } else {
            modalMessage.textContent = message || "";
        }
        modalCancel.style.display = confirm ? "inline-flex" : "none";
        modalOk.textContent = confirm ? "OK" : "Close";
        modalOverlay.querySelector(".modal").classList.toggle("wide", Boolean(preformatted));
        openDialog(modalOverlay, modalOk);
        return new Promise((resolve) => {
            modalResolve = resolve;
        });
    }

    function closeModal(result) {
        if (!modalOverlay) {
            return;
        }
        closeDialog(modalOverlay);
        if (modalResolve) {
            modalResolve(result);
            modalResolve = null;
        }
    }

    function openResumeModal() {
        if (!resumeOverlay) {
            return;
        }
        openDialog(resumeOverlay, resumeSessionId);
        if (resumeError) {
            resumeError.textContent = "";
        }
        renderRecentSessions();
        if (resumeSessionId) {
            resumeSessionId.value = "";
            resumeSessionId.focus();
        }
        if (resumePassphrase) {
            resumePassphrase.value = "";
        }
    }

    function closeResumeModal() {
        if (!resumeOverlay) {
            return;
        }
        closeDialog(resumeOverlay);
    }

    async function submitExit() {
        const res = await postAction({ action: "exit_session" });
        if (res && res.error) {
            await showModal({
                title: "Exit Failed",
                message: res.error,
                confirm: false
            });
            return;
        }
        const message = res && res.encrypted
            ? "Session exited and encrypted. A new session has started. Use Resume Session to reopen the previous session."
            : "Session exited. No passphrase was set, so the previous session was not encrypted.";
        await showModal({
            title: "Session Exited",
            message,
            confirm: false
        });
        location.reload();
    }

    function openLockModal() {
        if (!lockOverlay) {
            return;
        }
        openDialog(lockOverlay, lockPassphrase);
        if (lockPassphrase) {
            lockPassphrase.value = "";
            lockPassphrase.focus();
        }
    }

    function closeLockModal() {
        if (!lockOverlay) {
            return;
        }
        closeDialog(lockOverlay);
    }

    async function submitEncrypt() {
        const passphrase = (lockPassphrase?.value || "").trim();
        const res = await postAction({
            action: "encrypt_session",
            passphrase
        });
        if (res && res.error) {
            await showModal({
                title: "Encrypt Failed",
                message: res.error,
                confirm: false
            });
            return;
        }
        closeLockModal();
        await showModal({
            title: "Session Encrypted",
            message: "Session data encrypted with the new passphrase.",
            confirm: false
        });
        if (sessionStatus) {
            sessionStatus.textContent = "Encrypted";
            sessionStatus.classList.remove("plain");
            sessionStatus.classList.add("encrypted");
        }
    }

    async function refreshSessionStatus() {
        if (!sessionStatus) {
            return;
        }
        try {
            const res = await postAction({ action: "session_status" });
            if (res && typeof res.encrypted === "boolean") {
                sessionStatus.textContent = res.encrypted ? "Encrypted" : "Not Encrypted";
                sessionStatus.classList.toggle("encrypted", res.encrypted);
                sessionStatus.classList.toggle("plain", !res.encrypted);
            }
        } catch (err) {
        }
    }

    async function submitResume() {
        const sessionId = (resumeSessionId?.value || "").trim().toLowerCase();
        const passphrase = (resumePassphrase?.value || "").trim();
        if (!/^[a-f0-9]{32}$/.test(sessionId)) {
            if (resumeError) {
                resumeError.textContent = "Session ID must be exactly 32 lowercase hex characters.";
            }
            return;
        }
        if (resumeError) {
            resumeError.textContent = "";
        }
        const res = await postAction({
            action: "resume_session",
            session_id: sessionId,
            passphrase
        });
        if (res && res.error) {
            if (resumeError) {
                resumeError.textContent = res.error;
            }
            return;
        }
        rememberSessionId(sessionId);
        closeResumeModal();
        location.reload();
    }

    if (modalOk && modalCancel && modalOverlay) {
        modalOk.addEventListener("click", () => closeModal(true));
        modalCancel.addEventListener("click", () => closeModal(false));
        modalOverlay.addEventListener("click", (event) => {
            if (event.target === modalOverlay) {
                closeModal(false);
            }
        });
    }
    document.addEventListener("keydown", (event) => {
        if (!activeDialogOverlay || activeDialogOverlay.classList.contains("hidden")) {
            return;
        }
        if (event.key === "Escape") {
            if (activeDialogOverlay === modalOverlay) {
                closeModal(false);
            } else if (activeDialogOverlay === resumeOverlay) {
                closeResumeModal();
            } else if (activeDialogOverlay === lockOverlay) {
                closeLockModal();
            }
            return;
        }
        if (event.key !== "Tab") {
            return;
        }
        const focusable = getFocusableElements(activeDialogOverlay);
        if (focusable.length === 0) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const current = document.activeElement;
        if (event.shiftKey && current === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && current === last) {
            event.preventDefault();
            first.focus();
        }
    });
    if (resumeBtn) {
        resumeBtn.addEventListener("click", openResumeModal);
    }
    if (resumeOk) {
        resumeOk.addEventListener("click", submitResume);
    }
    if (resumeSessionId) {
        resumeSessionId.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                submitResume();
            }
        });
    }
    if (resumePassphrase) {
        resumePassphrase.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                submitResume();
            }
        });
    }
    if (resumeCancel) {
        resumeCancel.addEventListener("click", closeResumeModal);
    }
    if (resumeOverlay) {
        resumeOverlay.addEventListener("click", (event) => {
            if (event.target === resumeOverlay) {
                closeResumeModal();
            }
        });
    }
    if (lockBtn) {
        lockBtn.addEventListener("click", submitExit);
    }
    if (encryptBtn) {
        encryptBtn.addEventListener("click", openLockModal);
    }
    if (lockOk) {
        lockOk.addEventListener("click", submitEncrypt);
    }
    if (lockPassphrase) {
        lockPassphrase.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                submitEncrypt();
            }
        });
    }
    if (lockCancel) {
        lockCancel.addEventListener("click", closeLockModal);
    }
    if (lockOverlay) {
        lockOverlay.addEventListener("click", (event) => {
            if (event.target === lockOverlay) {
                closeLockModal();
            }
        });
    }

    async function parseJsonResponse(res) {
        const raw = await res.text();
        let parsed = null;
        if (raw) {
            try {
                parsed = JSON.parse(raw);
            } catch (err) {
                parsed = null;
            }
        }

        if (!res.ok) {
            const message = (parsed && parsed.error) || `Request failed (${res.status})`;
            return { error: message };
        }

        if (parsed === null) {
            return { error: "Invalid server response." };
        }

        return parsed;
    }

    async function postAction(payload) {
        const bodyPayload = { ...payload };
        if (!bodyPayload.csrf_token) {
            bodyPayload.csrf_token = csrfToken;
        }
        const body = new URLSearchParams(bodyPayload);
        const res = await fetch("", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body
        });
        return parseJsonResponse(res);
    }

    async function postFormData(formData) {
        if (!formData.has("csrf_token")) {
            formData.append("csrf_token", csrfToken);
        }
        const res = await fetch("", {
            method: "POST",
            body: formData
        });
        return parseJsonResponse(res);
    }

    function clearHistoryContainer() {
        historyContainer.innerHTML = "";
    }

    function renderEmptyHistory() {
        const empty = document.createElement("p");
        empty.className = "empty-history";
        empty.textContent = "No history yet.";
        historyContainer.appendChild(empty);
    }

    function renderHistoryEntry(entry) {
        const wrapper = document.createElement("div");
        wrapper.className = "history-entry";

        const time = document.createElement("div");
        time.className = "history-time";
        time.textContent = entry.created_at || "";

        const grid = document.createElement("div");
        grid.className = "history-grid";

        const original = document.createElement("div");
        original.className = "history-block";
        original.innerHTML = "<strong>Original</strong>";
        const originalText = document.createElement("pre");
        originalText.textContent = entry.original_prompt || "";
        original.appendChild(originalText);

        const scrubbed = document.createElement("div");
        scrubbed.className = "history-block";
        scrubbed.innerHTML = "<strong>Scrubbed</strong>";
        const scrubbedText = document.createElement("pre");
        scrubbedText.textContent = entry.scrubbed_prompt || "";
        scrubbed.appendChild(scrubbedText);

        const restored = document.createElement("div");
        restored.className = "history-block";
        restored.innerHTML = "<strong>Restored</strong>";
        const restoredText = document.createElement("pre");
        restoredText.textContent = entry.restored_response || "";
        restored.appendChild(restoredText);

        grid.appendChild(original);
        grid.appendChild(scrubbed);
        grid.appendChild(restored);

        wrapper.appendChild(time);
        wrapper.appendChild(grid);

        historyContainer.appendChild(wrapper);
    }

    async function updateHistory() {
        try {
            const res = await postAction({ action: "history" });
            if (res && res.error) {
                throw new Error(res.error);
            }
            clearHistoryContainer();

            if (!Array.isArray(res) || res.length === 0) {
                renderEmptyHistory();
                return;
            }

            res.forEach(renderHistoryEntry);
        } catch (err) {
            clearHistoryContainer();
            renderEmptyHistory();
        }
    }

    function renderRulesets(items) {
        if (!rulesetList) {
            return;
        }
        rulesetList.innerHTML = "";
        if (!Array.isArray(items) || items.length === 0) {
            const empty = document.createElement("p");
            empty.className = "empty-history";
            empty.textContent = "No rulesets found.";
            rulesetList.appendChild(empty);
            return;
        }

        items.forEach((item) => {
            const wrapper = document.createElement("div");
            wrapper.className = "ruleset-item";

            const meta = document.createElement("div");
            meta.className = "ruleset-meta";

            const title = document.createElement("div");
            title.className = "ruleset-title";
            title.textContent = `${item.ruleset_id} v${item.version || "0.0.0"}`;

            const desc = document.createElement("div");
            desc.className = "ruleset-desc";
            desc.textContent = item.description || "No description";

            const status = document.createElement("div");
            status.className = "ruleset-status";
            status.textContent = item.valid ? "Valid" : "Errors";
            if (!item.valid) {
                status.classList.add("bad");
            }

            meta.appendChild(title);
            meta.appendChild(desc);
            meta.appendChild(status);

            const controls = document.createElement("div");
            controls.className = "ruleset-controls";

            const toggle = document.createElement("label");
            toggle.className = "toggle";

            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.checked = Boolean(item.enabled);
            checkbox.addEventListener("change", async () => {
                await postAction({
                    action: "toggle_ruleset",
                    ruleset_id: item.ruleset_id,
                    enabled: checkbox.checked ? "1" : "0"
                });
            });

            const label = document.createElement("span");
            label.textContent = checkbox.checked ? "Enabled" : "Disabled";
            checkbox.addEventListener("change", () => {
                label.textContent = checkbox.checked ? "Enabled" : "Disabled";
            });

            toggle.appendChild(checkbox);
            toggle.appendChild(label);

            const download = document.createElement("a");
            download.className = "ruleset-link";
            download.href = `?action=download&ruleset_id=${encodeURIComponent(item.ruleset_id)}`;
            download.textContent = "Download";

            const viewBtn = document.createElement("button");
            viewBtn.className = "secondary small";
            viewBtn.textContent = "View";
            viewBtn.addEventListener("click", async () => {
                const res = await postAction({
                    action: "view_ruleset",
                    ruleset_id: item.ruleset_id
                });
                if (res && res.error) {
                    await showModal({
                        title: "View Failed",
                        message: res.error,
                        confirm: false
                    });
                    return;
                }
                await showModal({
                    title: `Ruleset: ${item.ruleset_id}`,
                    message: res.content || "",
                    confirm: false,
                    preformatted: true
                });
            });

            const deleteBtn = document.createElement("button");
            deleteBtn.className = "danger small";
            deleteBtn.textContent = "Delete";
            deleteBtn.addEventListener("click", async () => {
                const ok = await showModal({
                    title: "Delete Ruleset",
                    message: `Delete ruleset ${item.ruleset_id}? This cannot be undone.`,
                    confirm: true
                });
                if (!ok) {
                    return;
                }
                const res = await postAction({
                    action: "delete_ruleset",
                    ruleset_id: item.ruleset_id
                });
                if (res && res.error) {
                    await showModal({
                        title: "Delete Failed",
                        message: res.error,
                        confirm: false
                    });
                    return;
                }
                await loadRulesets();
            });

            controls.appendChild(toggle);
            controls.appendChild(download);
            controls.appendChild(viewBtn);
            controls.appendChild(deleteBtn);

            wrapper.appendChild(meta);
            wrapper.appendChild(controls);

            if (Array.isArray(item.errors) && item.errors.length > 0) {
                const errorBox = document.createElement("div");
                errorBox.className = "ruleset-errors";
                const ul = document.createElement("ul");
                item.errors.forEach((err) => {
                    const li = document.createElement("li");
                    li.textContent = err;
                    ul.appendChild(li);
                });
                errorBox.appendChild(ul);
                wrapper.appendChild(errorBox);
            }

            rulesetList.appendChild(wrapper);
        });
    }

    async function loadRulesets() {
        try {
            const res = await postAction({ action: "rulesets" });
            if (res && res.error) {
                throw new Error(res.error);
            }
            renderRulesets(res);
        } catch (err) {
            renderRulesets([]);
        }
    }

    async function handleScrub() {
        const raw = rawInput.value || "";
        if (!raw.trim()) {
            await showModal({
                title: "Missing Input",
                message: "Please paste or type content into Raw Input before scrubbing.",
                confirm: false
            });
            return;
        }
        setLoading(true);
        try {
            const res = await postAction({ action: "scrub", text: raw });
            if (res && res.error) {
                await showModal({
                    title: "Scrub Failed",
                    message: res.error,
                    confirm: false
                });
                return;
            }
            scrubbedOutput.value = res.scrubbed_text || "";
            const count = typeof res.count === "number" ? res.count : 0;
            scrubStats.textContent = `Replacements: ${count}`;
            showPaneFeedback("scrubbedOutput", `Scrubbed ${count} replacement(s).`, "success");
            refreshAllTextareaMeta();
            await updateHistory();
        } finally {
            setLoading(false);
        }
    }

    async function handleRestore() {
        const llm = llmInput.value || "";
        if (!llm.trim()) {
            await showModal({
                title: "Missing Input",
                message: "Please paste or type content into LLM Response before restoring.",
                confirm: false
            });
            return;
        }
        setLoading(true);
        try {
            const res = await postAction({ action: "restore", text: llm });
            if (res && res.error) {
                await showModal({
                    title: "Restore Failed",
                    message: res.error,
                    confirm: false
                });
                return;
            }
            restoredOutput.value = res.restored_text || "";
            showPaneFeedback("restoredOutput", "Restored output ready.", "success");
            refreshAllTextareaMeta();
            await updateHistory();
            updateQuickTestEnabled();
        } finally {
            setLoading(false);
        }
    }

    async function handleClear() {
        const ok = await showModal({
            title: "Clear Session",
            message: "Clear current session and delete its stored mappings and history?",
            confirm: true
        });
        if (!ok) {
            return;
        }
        const res = await postAction({ action: "clear" });
        if (res && res.error) {
            await showModal({
                title: "Clear Failed",
                message: res.error,
                confirm: false
            });
            return;
        }
        location.reload();
    }

    async function handleUploadRuleset() {
        if (!rulesetFile || !rulesetFile.files || rulesetFile.files.length === 0) {
            await showModal({
                title: "Missing File",
                message: "Please choose a ruleset file to upload.",
                confirm: false
            });
            return;
        }

        const formData = new FormData();
        formData.append("action", "upload_ruleset");
        formData.append("ruleset", rulesetFile.files[0]);

        let res;
        try {
            res = await postFormData(formData);
        } catch (err) {
            await showModal({
                title: "Upload Failed",
                message: "Network error while uploading ruleset.",
                confirm: false
            });
            return;
        }
        if (res && res.error) {
            await showModal({
                title: "Upload Failed",
                message: res.error,
                confirm: false
            });
            return;
        }

        rulesetFile.value = "";
        await loadRulesets();
    }

    function setQuickTestStatus(text, className) {
        if (!quickTestStatus) {
            return;
        }
        quickTestStatus.textContent = text;
        quickTestStatus.className = className || "";
        announceForA11y(text);
    }

    function buildMismatchPreview(raw, restored) {
        const maxContext = 60;
        let idx = -1;
        const limit = Math.min(raw.length, restored.length);
        for (let i = 0; i < limit; i += 1) {
            if (raw[i] !== restored[i]) {
                idx = i;
                break;
            }
        }
        if (idx === -1) {
            idx = limit;
        }
        const start = Math.max(0, idx - maxContext);
        const rawSnippet = raw.slice(start, idx + maxContext);
        const restoredSnippet = restored.slice(start, idx + maxContext);
        return [
            `First mismatch index: ${idx}`,
            "",
            "Raw:",
            rawSnippet,
            "",
            "Restored:",
            restoredSnippet
        ].join("\n");
    }

    async function handleQuickTest() {
        const raw = rawInput.value || "";
        const restored = restoredOutput.value || "";

        if (!restored.trim()) {
            await showModal({
                title: "Restore Required",
                message: "Please restore an LLM response before running Quick Test.",
                confirm: false
            });
            return;
        }

        if (!raw || !restored) {
            setQuickTestStatus("Quick Test: missing raw input or restored output.", "status-warn");
            return;
        }

        if (raw === restored) {
            setQuickTestStatus("Quick Test: exact match.", "status-ok");
            return;
        }

        const lengthDiff = Math.abs(raw.length - restored.length);
        setQuickTestStatus(`Quick Test: mismatch (length diff ${lengthDiff}).`, "status-bad");
        await showModal({
            title: "Quick Test Mismatch",
            message: buildMismatchPreview(raw, restored),
            confirm: false,
            preformatted: true
        });
    }

    function copyWithExecCommand(text) {
        const temp = document.createElement("textarea");
        temp.value = text;
        temp.setAttribute("readonly", "");
        temp.style.position = "fixed";
        temp.style.opacity = "0";
        temp.style.pointerEvents = "none";
        document.body.appendChild(temp);
        temp.focus();
        temp.select();
        const copied = document.execCommand("copy");
        document.body.removeChild(temp);
        return copied;
    }

    async function handleCopy(targetId, overrideText = "") {
        const el = document.getElementById(targetId);
        const text = overrideText || (el?.value ?? el?.textContent ?? "");
        if (!text) {
            showPaneFeedback(targetId, "Nothing to copy.", "warn");
            return;
        }

        const canUseAsyncClipboard = window.isSecureContext && Boolean(navigator.clipboard?.writeText);
        if (!canUseAsyncClipboard) {
            const copied = copyWithExecCommand(text);
            showStatusToast(copied ? "Copied" : "Copy failed", copied ? "success" : "error");
            showPaneFeedback(targetId, copied ? "Copied." : "Copy failed.", copied ? "success" : "error");
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            showStatusToast("Copied", "success");
            showPaneFeedback(targetId, "Copied.", "success");
        } catch (err) {
            const copied = copyWithExecCommand(text);
            showStatusToast(copied ? "Copied" : "Copy failed", copied ? "success" : "error");
            showPaneFeedback(targetId, copied ? "Copied." : "Copy failed.", copied ? "success" : "error");
        }
    }

    async function handlePaste(targetId) {
        const el = document.getElementById(targetId);
        if (!el) {
            return;
        }

        const focusForManualPaste = () => {
            el.focus();
            if (typeof el.setSelectionRange === "function") {
                const end = (el.value || "").length;
                el.setSelectionRange(end, end);
            }
        };

        if (!navigator.clipboard?.readText) {
            focusForManualPaste();
            showStatusToast("Press Ctrl+V (Cmd+V on Mac) to paste", "warn");
            showPaneFeedback(targetId, "Browser requires manual paste (Ctrl+V/Cmd+V).", "warn");
            return false;
        }
        try {
            const text = await navigator.clipboard.readText();
            el.value = text;
            showStatusToast("Pasted", "success");
            showPaneFeedback(targetId, "Pasted.", "success");
            refreshAllTextareaMeta();
            return true;
        } catch (err) {
            focusForManualPaste();
            showStatusToast("Browser blocked auto-paste. Press Ctrl+V.", "warn");
            showPaneFeedback(targetId, "Auto-paste blocked; use Ctrl+V/Cmd+V.", "warn");
            return false;
        }
    }

    document.querySelectorAll(".pane-actions button").forEach((btn) => {
        btn.addEventListener("click", async () => {
            const action = btn.getAttribute("data-action");
            const target = btn.getAttribute("data-target");
            if (!target) {
                return;
            }
            if (action === "copy") {
                await runWithButtonBusy(btn, "Copying", async () => {
                    await handleCopy(target);
                });
            } else if (action === "paste") {
                await runWithButtonBusy(btn, "Pasting", async () => {
                    await handlePaste(target);
                });
            }
        });
    });

    scrubBtn.addEventListener("click", () => runWithButtonBusy(scrubBtn, "Scrubbing", handleScrub));
    restoreBtn.addEventListener("click", () => runWithButtonBusy(restoreBtn, "Restoring", handleRestore));
    clearBtn.addEventListener("click", () => runWithButtonBusy(clearBtn, "Clearing", handleClear));
    quickTestBtn.addEventListener("click", handleQuickTest);
    refreshHistoryBtn.addEventListener("click", () => runWithButtonBusy(refreshHistoryBtn, "Refreshing", updateHistory));
    if (uploadRulesetBtn) {
        uploadRulesetBtn.addEventListener("click", () => runWithButtonBusy(uploadRulesetBtn, "Uploading", handleUploadRuleset));
    }
    if (backupRulesBtn) {
        backupRulesBtn.addEventListener("click", () => {
            window.location.href = "?action=download_rules_backup";
        });
    }
    if (copySessionBtn) {
        copySessionBtn.addEventListener("click", async () => {
            const sessionIdText = document.getElementById("sessionId")?.textContent || "";
            await handleCopy("", sessionIdText);
        });
    }
    if (pasteScrubBtn) {
        pasteScrubBtn.addEventListener("click", () => runWithButtonBusy(pasteScrubBtn, "Running", async () => {
            await handlePaste("rawInput");
            if ((rawInput?.value || "").trim()) {
                await handleScrub();
            }
        }));
    }
    if (pasteRestoreBtn) {
        pasteRestoreBtn.addEventListener("click", () => runWithButtonBusy(pasteRestoreBtn, "Running", async () => {
            await handlePaste("llmInput");
            if ((llmInput?.value || "").trim()) {
                await handleRestore();
            }
        }));
    }
    if (rawInput) {
        rawInput.addEventListener("input", refreshAllTextareaMeta);
    }
    if (llmInput) {
        llmInput.addEventListener("input", refreshAllTextareaMeta);
    }
    restoredOutput.addEventListener("input", updateQuickTestEnabled);

    updateHistory();
    updateQuickTestEnabled();
    refreshAllTextareaMeta();
    const currentSessionId = (document.getElementById("sessionId")?.textContent || "").trim().toLowerCase();
    rememberSessionId(currentSessionId);
    loadRulesets();
    refreshSessionStatus();
})();
