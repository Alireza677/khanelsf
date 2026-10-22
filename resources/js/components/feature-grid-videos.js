// Only provider-generated HTTPS frame URLs may reach the player, even after DOM mutation.
export const safeVideoEmbed = (value) => {
    try {
        const url = new URL(value);
        if (url.origin !== 'https://www.aparat.com' || url.username || url.password || url.search || url.hash
            || ! /^\/video\/video\/embed\/videohash\/[A-Za-z0-9_-]{1,100}\/vt\/frame$/.test(url.pathname)) return null;
        return url.href;
    } catch {
        return null;
    }
};

export const initFeatureGridVideos = () => {
    if (document.documentElement.dataset.featureVideosReady === 'true') return;
    document.documentElement.dataset.featureVideosReady = 'true';
    let dialog;
    let player;
    let heading;
    let closeButton;
    let opener;

    const createDialog = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'feature-video-dialog';
        dialog.dir = 'rtl';
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'feature-video-dialog-title');
        const panel = document.createElement('div');
        panel.className = 'feature-video-dialog__panel';
        const header = document.createElement('div');
        header.className = 'feature-video-dialog__header';
        heading = document.createElement('h2');
        heading.id = 'feature-video-dialog-title';
        closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.textContent = 'بستن';
        closeButton.setAttribute('aria-label', 'بستن پخش ویدئو');
        player = document.createElement('div');
        player.className = 'feature-video-dialog__player';
        header.append(heading, closeButton);
        panel.append(header, player);
        dialog.append(panel);
        document.body.append(dialog);

        // Native showModal supplies focus containment and an inert background, including keyboard navigation.
        closeButton.addEventListener('click', () => dialog.close());
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            dialog.close();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => {
            player.replaceChildren(); // Remove the iframe to stop playback and all player requests.
            document.documentElement.classList.remove('feature-video-dialog-open');
            if (opener?.isConnected) opener.focus({ preventScroll: true });
            opener = null;
        });
        window.addEventListener('pagehide', () => {
            player.replaceChildren();
            if (dialog.open) dialog.close();
        });
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-feature-video-play]');
        if (! trigger || event.defaultPrevented) return;
        const embed = safeVideoEmbed(trigger.dataset.videoEmbed);
        if (! embed) return;
        event.preventDefault();
        if (! dialog) createDialog();
        if (typeof dialog.showModal !== 'function') return;
        opener = trigger;
        heading.textContent = trigger.dataset.videoTitle || 'پخش ویدئو';
        player.replaceChildren();
        dialog.showModal();
        document.documentElement.classList.add('feature-video-dialog-open');
        closeButton.focus({ preventScroll: true });
        const frame = document.createElement('iframe');
        frame.title = heading.textContent;
        frame.allow = 'autoplay; fullscreen; picture-in-picture';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.src = embed;
        player.append(frame);
    });
};
