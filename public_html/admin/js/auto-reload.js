/**
 * FlexPBX Auto-Reload Helper - Smart reload with active call detection
 */
class FlexPBXReload {
    async reload(module = 'all', options = {}) {
        const { force = false, onProgress = null, onComplete = null, onError = null } = options;
        try {
            if (!force) {
                const callCheck = await fetch('/api/system.php?path=active_calls');
                const callData = await callCheck.json();
                if (callData.has_active_calls) {
                    const shouldWait = confirm(`There are ${callData.active_calls} active call(s).\n\nWait for calls to finish?`);
                    if (shouldWait) return await this.waitAndReload(module, onProgress, onComplete, onError);
                }
            }
            return await this.performReload(module, force, onComplete, onError);
        } catch (error) {
            if (onError) onError(error);
            return false;
        }
    }

    async performReload(module, force, onComplete, onError) {
        try {
            const response = await fetch('/api/system.php?path=reload', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ module, force })
            });
            const data = await response.json();
            if (data.success) {
                if (onComplete) onComplete(data);
                return true;
            } else {
                if (onError) onError(new Error(data.message));
                return false;
            }
        } catch (error) {
            if (onError) onError(error);
            return false;
        }
    }
}

window.flexPBXReload = new FlexPBXReload();
window.smartReload = async function(module = 'all') {
    const div = document.createElement('div');
    div.className = 'alert alert-info position-fixed top-0 start-50 translate-middle-x mt-3';
    div.style.zIndex = '9999';
    div.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Reloading...';
    document.body.appendChild(div);
    
    await window.flexPBXReload.reload(module, {
        onComplete: () => {
            div.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3';
            div.innerHTML = '<i class="fas fa-check me-2"></i>Reloaded successfully';
            setTimeout(() => { div.remove(); location.reload(); }, 1500);
        },
        onError: (e) => {
            div.className = 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3';
            div.innerHTML = '<i class="fas fa-times me-2"></i>Error: ' + e.message;
            setTimeout(() => div.remove(), 5000);
        }
    });
};
