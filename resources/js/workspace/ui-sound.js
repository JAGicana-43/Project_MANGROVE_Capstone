// Short synthesized feedback sounds for confirmations/state changes (no audio
// assets needed). Muted state persists via localStorage so it survives reloads
// and stays in sync with the speaker icon in the header.
(function () {
    var STORAGE_KEY = 'uiSoundMuted';
    var ctx = null;

    function isMuted() {
        return localStorage.getItem(STORAGE_KEY) === '1';
    }

    function getContext() {
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return null;
        if (!ctx) ctx = new AudioCtx();
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    function tone(audioCtx, freq, start, duration, peakGain, waveform) {
        var osc = audioCtx.createOscillator();
        var gain = audioCtx.createGain();
        osc.type = waveform || 'sine';
        osc.frequency.value = freq;
        var t0 = audioCtx.currentTime + start;
        gain.gain.setValueAtTime(0, t0);
        gain.gain.linearRampToValueAtTime(peakGain, t0 + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(t0);
        osc.stop(t0 + duration + 0.02);
    }

    var PRESETS = {
        success: function (audioCtx) {
            tone(audioCtx, 880, 0, 0.12, 0.12);
            tone(audioCtx, 1318.5, 0.09, 0.16, 0.1);
        },
        error: function (audioCtx) {
            tone(audioCtx, 320, 0, 0.16, 0.12, 'triangle');
            tone(audioCtx, 220, 0.1, 0.2, 0.1, 'triangle');
        },
        notify: function (audioCtx) {
            tone(audioCtx, 660, 0, 0.14, 0.1);
        }
    };

    window.uiSound = {
        play: function (name) {
            if (isMuted()) return;
            var audioCtx = getContext();
            if (!audioCtx) return;
            var preset = PRESETS[name] || PRESETS.notify;
            try { preset(audioCtx); } catch (e) {}
        },
        isMuted: isMuted,
        setMuted: function (muted) {
            localStorage.setItem(STORAGE_KEY, muted ? '1' : '0');
        },
        toggleMute: function () {
            var next = !isMuted();
            localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
            return next;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('soundToggle');
        var toggleIcon = document.getElementById('soundToggleIcon');
        var toggleText = document.getElementById('soundToggleText');
        if (!toggleBtn || !toggleIcon) return;

        function applyIcon(muted) {
            var label = muted ? 'Unmute interface sounds' : 'Mute interface sounds';
            toggleIcon.className = muted ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-volume-high';
            toggleBtn.title = label;
            if (toggleText) toggleText.textContent = label;
        }

        applyIcon(isMuted());

        toggleBtn.addEventListener('click', function () {
            var nowMuted = window.uiSound.toggleMute();
            applyIcon(nowMuted);
            if (!nowMuted) window.uiSound.play('notify');
        });
    });
})();
