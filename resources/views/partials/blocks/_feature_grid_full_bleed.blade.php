@once
    <script>
        (() => {
            const init = () => {
                const sections = [...document.querySelectorAll('[data-feature-grid-section]')];
                const root = document.documentElement;
                const update = () => {
                    // clientWidth excludes the scrollbar; keep each parent's existing content box.
                    const viewportWidth = root.clientWidth;
                    const viewportLeft = root.getBoundingClientRect().left + root.clientLeft;
                    const measurements = sections.map(section => {
                        const parent = section.parentElement;
                        const rect = parent.getBoundingClientRect();
                        const style = getComputedStyle(parent);
                        const left = rect.left + parent.clientLeft + parseFloat(style.paddingLeft) - viewportLeft;
                        const width = rect.width - (parent.offsetWidth - parent.clientWidth)
                            - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);

                        return { section, left, width, right: viewportWidth - left - width };
                    });

                    measurements.forEach(({ section, left, width, right }) => {
                        section.style.setProperty('--feature-grid-section-width', `${viewportWidth}px`);
                        section.style.setProperty('--feature-grid-inner-width', `${width}px`);
                        section.style.setProperty('--feature-grid-inset-left', `${left}px`);
                        section.style.setProperty('--feature-grid-inset-right', `${right}px`);
                    });
                };

                update();
                const observer = new ResizeObserver(update);
                observer.observe(root);
                new Set(sections.map(section => section.parentElement)).forEach(parent => observer.observe(parent));
                window.addEventListener('resize', update);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init, { once: true });
            } else {
                init();
            }
        })();
    </script>
@endonce
