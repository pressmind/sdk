<?php
/**
 * Reusable ProcessStream JS class for SSE command/import output.
 * Include once per page. Uses EventSource; compatible with Process\StreamExecutor server events (start, message, complete, error).
 * Vars: none (script only).
 */
?>
<script>
(function() {
    'use strict';

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * @param {string} logContainerId - ID of pre/div for log lines
     * @param {Object} options - statusId, startBtnId, closeBtnId, abortBtnId, onComplete(success, data)
     */
    window.ProcessStream = function(logContainerId, options) {
        options = options || {};
        this.logContainerId = logContainerId;
        this.statusId = options.statusId || logContainerId + '-status';
        this.startBtnId = options.startBtnId || null;
        this.closeBtnId = options.closeBtnId || null;
        this.abortBtnId = options.abortBtnId || null;
        this.onComplete = options.onComplete || null;
        this._es = null;
        this._finished = false;
    };

    ProcessStream.prototype._getLogEl = function() {
        return document.getElementById(this.logContainerId);
    };

    ProcessStream.prototype._getStatusEl = function() {
        return document.getElementById(this.statusId);
    };

    ProcessStream.prototype.appendLog = function(text, type) {
        var logEl = this._getLogEl();
        if (!logEl) return;
        type = type || 'line';
        var span = document.createElement('span');
        span.textContent = text;
        if (type === 'error') span.className = 'text-danger';
        if (type === 'success') span.className = 'text-success';
        logEl.appendChild(span);
        logEl.scrollTop = logEl.scrollHeight;
    };

    ProcessStream.prototype._setStatus = function(text, badgeClass) {
        var el = this._getStatusEl();
        if (el) {
            el.textContent = text;
            el.className = 'badge ' + (badgeClass || 'bg-secondary');
        }
    };

    ProcessStream.prototype._setRunning = function() {
        this._setStatus('Running…', 'bg-primary');
        if (this.startBtnId) {
            var btn = document.getElementById(this.startBtnId);
            if (btn) btn.disabled = true;
        }
        if (this.closeBtnId) {
            var closeBtn = document.getElementById(this.closeBtnId);
            if (closeBtn) closeBtn.disabled = true;
        }
        if (this.abortBtnId) {
            var abortBtn = document.getElementById(this.abortBtnId);
            if (abortBtn) abortBtn.disabled = false;
        }
    };

    ProcessStream.prototype._setFinished = function(success, message) {
        this._finished = true;
        if (this._es) {
            this._es.close();
            this._es = null;
        }
        this._setStatus(success ? 'Done' : (message === 'Aborted' ? 'Aborted' : 'Failed'), success ? 'bg-success' : 'bg-danger');
        if (this.startBtnId) {
            var btn = document.getElementById(this.startBtnId);
            if (btn) btn.disabled = false;
        }
        if (this.closeBtnId) {
            var closeBtn = document.getElementById(this.closeBtnId);
            if (closeBtn) {
                closeBtn.disabled = false;
                closeBtn.classList.remove('d-none');
            }
        }
        if (this.abortBtnId) {
            var abortBtn = document.getElementById(this.abortBtnId);
            if (abortBtn) {
                abortBtn.disabled = true;
            }
        }
        if (this.onComplete) this.onComplete(success, message);
    };

    ProcessStream.prototype.start = function(url) {
        if (this._es) return;
        this._finished = false;
        var logEl = this._getLogEl();
        if (logEl) logEl.innerHTML = '';
        this._setRunning();

        var self = this;
        this._es = new EventSource(url);

        this._es.addEventListener('start', function() {
            if (self._finished) return;
            self._setStatus('Running…', 'bg-primary');
        });

        this._es.onmessage = function(event) {
            if (self._finished) return;
            try {
                var d = JSON.parse(event.data);
                if (d.text) self.appendLog(d.text, d.type || 'line');
            } catch (e) {
                self.appendLog(event.data, 'line');
            }
        };

        this._es.addEventListener('complete', function(event) {
            if (self._finished) return;
            try {
                var d = JSON.parse(event.data);
                if (d.success === false && d.message) {
                    self.appendLog('\n' + d.message, 'error');
                }
                self._setFinished(d.success !== false, d.message || (d.success ? 'Done' : 'Failed'));
            } catch (e) {
                self._setFinished(false, 'Invalid response');
            }
        });

        this._es.addEventListener('error', function(event) {
            if (self._finished) return;
            try {
                var d = event.data ? JSON.parse(event.data) : {};
                if (d.message) self.appendLog(d.message, 'error');
                self._setFinished(false, d.message || 'Error');
            } catch (e) {
                // connection error
                self._setFinished(false, 'Connection error');
            }
        });

        this._es.onerror = function() {
            if (self._finished) return;
            self._setFinished(false, 'Connection error');
        };
    };

    ProcessStream.prototype.abort = function() {
        this._finished = true;
        if (this._es) {
            this._es.close();
            this._es = null;
        }
        this.appendLog('Aborted by user', 'error');
        this._setFinished(false, 'Aborted');
    };

    /**
     * Reset log output and status to initial state (e.g. when user clicks Close after run).
     */
    ProcessStream.prototype.reset = function() {
        this._finished = true;
        if (this._es) {
            this._es.close();
            this._es = null;
        }
        var logEl = this._getLogEl();
        if (logEl) logEl.innerHTML = '';
        this._setStatus('Ready', 'bg-secondary');
        if (this.startBtnId) {
            var btn = document.getElementById(this.startBtnId);
            if (btn) btn.disabled = false;
        }
        if (this.closeBtnId) {
            var closeBtn = document.getElementById(this.closeBtnId);
            if (closeBtn) {
                closeBtn.classList.add('d-none');
                closeBtn.disabled = false;
            }
        }
        if (this.abortBtnId) {
            var abortBtn = document.getElementById(this.abortBtnId);
            if (abortBtn) abortBtn.disabled = true;
        }
    };
})();
</script>
