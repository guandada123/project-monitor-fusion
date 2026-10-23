/**
 * Project Monitor — Three.js Immersive Background
 *
 * Floating geometric particles with theme-aware colors,
 * mouse parallax, and performance-adaptive particle count.
 *
 * Self-initializing on DOMContentLoaded.
 * Dynamically imported — Three.js is lazy-loaded as a separate chunk.
 */

import * as THREE from 'three';

let scene, camera, renderer;
let particles = [];
let mouseX = 0, mouseY = 0;
let frameId = null;
let isLowPerf = false;
// 主题变更观察者。2026-10-16 审计修复（P1）：必须登记为模块级变量，
// 原先它是 init 内的局部 const，destroy 时无从 disconnect —— 每次导航泄漏一个。
let themeObserver = null;

// ── Configuration ──────────────────────────────────────────────
const CONFIG = {
    particleCount: () => isLowPerf ? 30 : 70,
    geometryTypes: ['tetrahedron', 'octahedron', 'dodecahedron', 'sphere'],
    sizes: [0.06, 0.10, 0.14, 0.18],
    floatAmplitude: 0.15,
    floatSpeed: 0.002,
    rotationSpeed: 0.004,
    mouseInfluence: 0.0004,
    colors: {
        light: [
            { h: 0.60, s: 0.50, l: 0.75 },   // soft blue
            { h: 0.68, s: 0.40, l: 0.80 },   // soft lavender
            { h: 0.55, s: 0.45, l: 0.78 },   // sky
            { h: 0.72, s: 0.35, l: 0.82 },   // lilac
        ],
        dark: [
            { h: 0.60, s: 0.60, l: 0.35 },   // blue
            { h: 0.68, s: 0.50, l: 0.30 },   // purple
            { h: 0.55, s: 0.55, l: 0.32 },   // indigo
            { h: 0.72, s: 0.45, l: 0.28 },   // violet
        ],
    },
};

// ── Utility ────────────────────────────────────────────────────
function hslToRgb(h, s, l) {
    // h: 0-1, s: 0-1, l: 0-1
    let r, g, b;
    if (s === 0) {
        r = g = b = l;
    } else {
        const hue2rgb = (p, q, t) => {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1/6) return p + (q - p) * 6 * t;
            if (t < 1/2) return q;
            if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
            return p;
        };
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        r = hue2rgb(p, q, h + 1/3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1/3);
    }
    return { r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255) };
}

function isDarkTheme() {
    return document.documentElement.classList.contains('dark');
}

function detectLowPerf() {
    // Detect low-end devices or reduced motion preference
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    const lowMemory = navigator.deviceMemory && navigator.deviceMemory < 4;
    return prefersReduced || (isMobile && lowMemory);
}

// ── Scene Setup ────────────────────────────────────────────────
function createScene() {
    const container = document.getElementById('three-background');
    if (!container) return;

    scene = new THREE.Scene();

    // Camera — perspective with enough depth for floating particles
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 100);
    camera.position.z = 2.5;

    // Renderer — transparent, no shadows for perf
    const dpr = Math.min(window.devicePixelRatio, 2);
    renderer = new THREE.WebGLRenderer({
        alpha: true,
        antialias: !isLowPerf,
        powerPreference: isLowPerf ? 'low-power' : 'high-performance',
    });
    renderer.setPixelRatio(dpr);
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setClearColor(0x000000, 0);
    container.appendChild(renderer.domElement);
}

// ── Particles ──────────────────────────────────────────────────
function createParticles() {
    const count = CONFIG.particleCount();
    const isDark = isDarkTheme();
    const palette = isDark ? CONFIG.colors.dark : CONFIG.colors.light;

    // Clean up existing particles
    particles.forEach(p => {
        scene.remove(p.mesh);
        p.geometry?.dispose();
        p.material?.dispose();
    });
    particles = [];

    const spread = 3.0;

    for (let i = 0; i < count; i++) {
        const geoType = CONFIG.geometryTypes[i % CONFIG.geometryTypes.length];
        const size = CONFIG.sizes[i % CONFIG.sizes.length] * (0.8 + Math.random() * 0.4);
        const color = palette[i % palette.length];
        const rgb = hslToRgb(color.h, color.s, color.l);

        let geometry;
        switch (geoType) {
            case 'tetrahedron':
                geometry = new THREE.TetrahedronGeometry(size);
                break;
            case 'octahedron':
                geometry = new THREE.OctahedronGeometry(size);
                break;
            case 'dodecahedron':
                geometry = new THREE.DodecahedronGeometry(size * 0.7);
                break;
            case 'sphere':
                geometry = new THREE.IcosahedronGeometry(size, 0);
                break;
            default:
                geometry = new THREE.OctahedronGeometry(size);
        }

        const material = new THREE.MeshPhysicalMaterial({
            color: new THREE.Color(`rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`),
            metalness: isDark ? 0.3 : 0.1,
            roughness: isDark ? 0.4 : 0.6,
            transparent: true,
            opacity: isDark ? 0.6 : 0.35,
            clearcoat: isDark ? 0.2 : 0,
            emissive: isDark ? new THREE.Color(`rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`) : new THREE.Color(0x000000),
            emissiveIntensity: isDark ? 0.08 : 0,
        });

        const mesh = new THREE.Mesh(geometry, material);

        // Random position in 3D space
        mesh.position.x = (Math.random() - 0.5) * spread;
        mesh.position.y = (Math.random() - 0.5) * spread;
        mesh.position.z = (Math.random() - 0.5) * 3;

        // Random rotation
        mesh.rotation.x = Math.random() * Math.PI * 2;
        mesh.rotation.y = Math.random() * Math.PI * 2;
        mesh.rotation.z = Math.random() * Math.PI * 2;

        scene.add(mesh);

        particles.push({
            mesh,
            geometry,
            material,
            floatOffset: Math.random() * Math.PI * 2,
            floatSpeed: 0.001 + Math.random() * 0.003,
            rotSpeedX: (Math.random() - 0.5) * CONFIG.rotationSpeed,
            rotSpeedY: (Math.random() - 0.5) * CONFIG.rotationSpeed,
            rotSpeedZ: (Math.random() - 0.5) * CONFIG.rotationSpeed,
            baseX: mesh.position.x,
            baseY: mesh.position.y,
            baseZ: mesh.position.z,
        });
    }
}

// ── Animation Loop ─────────────────────────────────────────────
function animate(time) {
    frameId = requestAnimationFrame(animate);

    const floatAmp = CONFIG.floatAmplitude;
    const floatSpd = CONFIG.floatSpeed;

    particles.forEach((p, i) => {
        const t = time * p.floatSpeed + p.floatOffset;

        // Floating motion on all axes
        p.mesh.position.x = p.baseX + Math.sin(t) * floatAmp + Math.cos(t * 0.7) * floatAmp * 0.5;
        p.mesh.position.y = p.baseY + Math.cos(t * 0.8) * floatAmp + Math.sin(t * 0.5) * floatAmp * 0.5;
        p.mesh.position.z = p.baseZ + Math.sin(t * 0.6 + 1) * floatAmp * 0.6;

        // Mouse parallax
        p.mesh.position.x += mouseX * CONFIG.mouseInfluence * (p.baseZ + 1.5) * 0.5;
        p.mesh.position.y += mouseY * CONFIG.mouseInfluence * (p.baseZ + 1.5) * 0.5;

        // Slow rotation
        p.mesh.rotation.x += p.rotSpeedX;
        p.mesh.rotation.y += p.rotSpeedY;
        p.mesh.rotation.z += p.rotSpeedZ;
    });

    renderer.render(scene, camera);
}

// ── Event Handlers ─────────────────────────────────────────────
function onResize() {
    if (!camera || !renderer) return;
    const w = window.innerWidth;
    const h = window.innerHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
}

function onMouseMove(e) {
    // Normalize mouse to [-1, 1] range
    mouseX = (e.clientX / window.innerWidth) * 2 - 1;
    mouseY = -(e.clientY / window.innerHeight) * 2 + 1;
}

function onThemeChange() {
    // Rebuild particles with the new theme colors
    if (!scene) return;
    createParticles();
}

// ── Init & Teardown ────────────────────────────────────────────
export function initThreeBackground() {
    // Check if container exists
    let container = document.getElementById('three-background');
    if (!container) {
        container = document.createElement('div');
        container.id = 'three-background';
        container.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        `;
        document.body.prepend(container);
    }

    // Performance detection
    isLowPerf = detectLowPerf();
    if (isLowPerf) {
        container.style.display = 'none';
        return;
    }

    // Populate initial mouse position at center
    mouseX = 0;
    mouseY = 0;

    createScene();

    if (!renderer) return;

    createParticles();

    animate(0);

    // Bind events —— 先摘后挂，使本函数幂等：重复 init 不会叠加监听
    window.removeEventListener('resize', onResize);
    window.removeEventListener('mousemove', onMouseMove);
    window.addEventListener('resize', onResize);
    window.addEventListener('mousemove', onMouseMove);

    // Watch for theme changes (MutationObserver on <html> class)
    themeObserver?.disconnect();
    themeObserver = new MutationObserver((mutations) => {
        for (const m of mutations) {
            if (m.attributeName === 'class') {
                onThemeChange();
                break;
            }
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}

export function destroyThreeBackground() {
    if (frameId) {
        cancelAnimationFrame(frameId);
        frameId = null;
    }

    particles.forEach(p => {
        scene?.remove(p.mesh);
        p.geometry?.dispose();
        p.material?.dispose();
    });
    particles = [];

    // 2026-10-16 审计修复（P1）：摘掉主题观察者，否则每次导航累积一个
    themeObserver?.disconnect();
    themeObserver = null;

    // renderer.dispose() 只释放 GPU 资源，不会把 canvas 从 DOM 移除；
    // 不显式摘掉的话，每次导航都会在 #three-background 里留下一个废弃 canvas
    if (renderer?.domElement?.parentNode) {
        renderer.domElement.parentNode.removeChild(renderer.domElement);
    }

    renderer?.dispose();
    renderer = null;
    scene = null;
    camera = null;

    window.removeEventListener('resize', onResize);
    window.removeEventListener('mousemove', onMouseMove);
}

// ── Livewire SPA lifecycle ─────────────────────────────────────
// 2026-10-16 审计修复（P1）：原先这两个监听注册在 initThreeBackground() 内部，
// 每导航一次就多两个常驻监听（destroy 并不摘它们，且 destroy→init 循环会再度注册）。
// 改为模块级注册一次。
if (typeof document !== 'undefined') {
    document.addEventListener('livewire:navigating', () => {
        destroyThreeBackground();
    });
    document.addEventListener('livewire:navigated', () => {
        // Re-create after navigation if needed
        if (!renderer) {
            initThreeBackground();
        }
    });
}

// Auto-init
if (typeof document !== 'undefined') {
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initThreeBackground();
    } else {
        document.addEventListener('DOMContentLoaded', initThreeBackground);
    }
}
