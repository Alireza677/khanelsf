import * as THREE from 'three';

const instances = new WeakMap();
const densityColumns = { low: 34, medium: 50, high: 68 };
const speedFactors = { slow: 0.35, normal: 0.65, fast: 1 };

const clampNumber = (value, minimum, maximum, fallback) => {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? Math.min(maximum, Math.max(minimum, parsed)) : fallback;
};

const createDottedSurface = (element) => {
    if (instances.has(element) || ! element.parentElement) {
        return;
    }

    const host = element.parentElement;
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const density = densityColumns[element.dataset.density] || densityColumns.medium;
    const columns = Math.max(20, density - (isMobile ? 18 : 0));
    const rows = Math.max(14, Math.round(columns * 0.58));
    const speed = speedFactors[element.dataset.speed] || speedFactors.slow;
    const opacity = clampNumber(element.dataset.opacity, 0.1, 1, 0.45);
    const interactive = element.dataset.interactive === 'true' && ! isMobile && ! reducedMotion;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: ! isMobile, powerPreference: 'low-power' });
    const geometry = new THREE.BufferGeometry();
    const positions = new Float32Array(columns * rows * 3);
    const basePositions = new Float32Array(columns * rows * 3);
    const material = new THREE.PointsMaterial({
        color: element.dataset.theme === 'light' ? 0x334155 : 0xe2e8f0,
        opacity,
        size: isMobile ? 0.035 : 0.045,
        sizeAttenuation: true,
        transparent: true,
        depthWrite: false,
    });

    for (let row = 0; row < rows; row += 1) {
        for (let column = 0; column < columns; column += 1) {
            const offset = (row * columns + column) * 3;
            const x = (column / (columns - 1) - 0.5) * 12;
            const z = (row / (rows - 1) - 0.5) * 7;
            positions[offset] = basePositions[offset] = x;
            positions[offset + 1] = basePositions[offset + 1] = 0;
            positions[offset + 2] = basePositions[offset + 2] = z;
        }
    }

    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const points = new THREE.Points(geometry, material);
    points.rotation.x = -0.16;
    scene.add(points);
    camera.position.set(0, 4.2, 7.8);
    camera.lookAt(0, 0, 0);
    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.domElement.setAttribute('aria-hidden', 'true');
    element.appendChild(renderer.domElement);

    let frameId = null;
    let visible = true;
    let destroyed = false;
    let targetPointerX = 0;
    let targetPointerY = 0;
    let pointerX = 0;
    let pointerY = 0;
    const startedAt = performance.now();

    const resize = () => {
        const { width, height } = element.getBoundingClientRect();

        if (width <= 0 || height <= 0) {
            return;
        }

        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    };

    const render = (now) => {
        frameId = null;

        if (destroyed || ! visible) {
            return;
        }

        const elapsed = (now - startedAt) * 0.001 * (reducedMotion ? 0.04 : speed);
        pointerX += (targetPointerX - pointerX) * 0.035;
        pointerY += (targetPointerY - pointerY) * 0.035;

        for (let index = 0; index < positions.length; index += 3) {
            const x = basePositions[index];
            const z = basePositions[index + 2];
            positions[index + 1] = Math.sin(x * 0.72 + elapsed) * 0.22
                + Math.cos(z * 1.05 + elapsed * 0.8) * 0.16
                + pointerY * 0.08;
        }

        geometry.attributes.position.needsUpdate = true;
        camera.position.x = pointerX * 0.45;
        camera.lookAt(pointerX * 0.12, pointerY * 0.08, 0);
        renderer.render(scene, camera);
        frameId = window.requestAnimationFrame(render);
    };

    const start = () => {
        if (! destroyed && visible && frameId === null) {
            frameId = window.requestAnimationFrame(render);
        }
    };

    const stop = () => {
        if (frameId !== null) {
            window.cancelAnimationFrame(frameId);
            frameId = null;
        }
    };

    const handlePointerMove = (event) => {
        const bounds = host.getBoundingClientRect();
        targetPointerX = ((event.clientX - bounds.left) / bounds.width - 0.5) * 2;
        targetPointerY = -((event.clientY - bounds.top) / bounds.height - 0.5) * 2;
    };

    const handlePointerLeave = () => {
        targetPointerX = 0;
        targetPointerY = 0;
    };

    const resizeObserver = new ResizeObserver(resize);
    const intersectionObserver = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        visible ? start() : stop();
    }, { threshold: 0.01 });

    resizeObserver.observe(element);
    intersectionObserver.observe(element);

    if (interactive) {
        host.addEventListener('pointermove', handlePointerMove, { passive: true });
        host.addEventListener('pointerleave', handlePointerLeave, { passive: true });
    }

    const destroy = () => {
        if (destroyed) {
            return;
        }

        destroyed = true;
        stop();
        resizeObserver.disconnect();
        intersectionObserver.disconnect();
        host.removeEventListener('pointermove', handlePointerMove);
        host.removeEventListener('pointerleave', handlePointerLeave);
        scene.remove(points);
        geometry.dispose();
        material.dispose();
        renderer.dispose();
        renderer.domElement.remove();
        instances.delete(element);
    };

    instances.set(element, { destroy });
    resize();
    start();
};

const initializeDottedSurfaces = (root = document) => {
    root.querySelectorAll?.('[data-hero-dotted-surface]').forEach(createDottedSurface);
};

const cleanupRemovedSurfaces = (node) => {
    if (! (node instanceof Element)) {
        return;
    }

    const surfaces = node.matches('[data-hero-dotted-surface]')
        ? [node]
        : node.querySelectorAll('[data-hero-dotted-surface]');

    surfaces.forEach((surface) => instances.get(surface)?.destroy());
};

const observeDocument = () => {
    initializeDottedSurfaces();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    if (node.matches('[data-hero-dotted-surface]')) {
                        createDottedSurface(node);
                    }

                    initializeDottedSurfaces(node);
                }
            });
            mutation.removedNodes.forEach(cleanupRemovedSurfaces);
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeDocument, { once: true });
} else {
    observeDocument();
}
