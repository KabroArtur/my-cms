import { fetchSiteSettings } from "../api/settings";

let cachedPayload = null;
let pendingRequest = null;

const ADMIN_THEME_STORAGE_KEY = "cms-admin-theme";
const themeModes = new Set(["light", "dark"]);
const lightPalettes = new Set(["slate", "sand", "forest"]);
const darkPalettes = new Set(["midnight", "ocean", "graphite"]);

const jsDateFormats = {
    "d.m.Y": { day: "2-digit", month: "2-digit", year: "numeric" },
    "Y-m-d": { year: "numeric", month: "2-digit", day: "2-digit" },
    "d/m/Y": { day: "2-digit", month: "2-digit", year: "numeric" },
};

const jsTimeFormats = {
    "H:i": { hour: "2-digit", minute: "2-digit", hour12: false },
    "H:i:s": {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false,
    },
    "h:i A": { hour: "2-digit", minute: "2-digit", hour12: true },
};

export async function loadCmsSettings(force = false) {
    if (cachedPayload && !force) {
        return cachedPayload;
    }

    if (pendingRequest && !force) {
        return pendingRequest;
    }

    pendingRequest = fetchSiteSettings()
        .then((payload) => {
            cachedPayload = payload.data;
            applyResolvedAdminTheme(
                resolveThemeState(cachedPayload.settings ?? {}),
            );

            return cachedPayload;
        })
        .catch((error) => {
            if (error?.response?.status === 403) {
                cachedPayload = fallbackSettingsPayload();
                applyResolvedAdminTheme(
                    resolveThemeState(cachedPayload.settings ?? {}),
                );

                return cachedPayload;
            }

            throw error;
        })
        .finally(() => {
            pendingRequest = null;
        });

    return pendingRequest;
}

export function rememberCmsSettings(payload) {
    cachedPayload = payload;
    applyResolvedAdminTheme(resolveThemeState(cachedPayload.settings ?? {}));

    return cachedPayload;
}

export function getAdminThemeState() {
    return resolveThemeState(cachedPayload?.settings ?? {});
}

export function toggleAdminThemeMode() {
    const state = resolveThemeState(cachedPayload?.settings ?? {});
    const nextMode = state.mode === "dark" ? "light" : "dark";
    const nextState = {
        ...state,
        mode: nextMode,
        palette: nextMode === "dark" ? state.darkPalette : state.lightPalette,
    };

    applyResolvedAdminTheme(nextState);
    writeThemePreference(nextState);

    return nextState;
}

export function formatCmsDateTime(value, settings = cachedPayload?.settings) {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "—";
    }

    const dateOptions =
        jsDateFormats[settings?.date_format || "d.m.Y"] ||
        jsDateFormats["d.m.Y"];
    const timeOptions =
        jsTimeFormats[settings?.time_format || "H:i"] || jsTimeFormats["H:i"];

    return new Intl.DateTimeFormat("ru-RU", {
        ...dateOptions,
        ...timeOptions,
    }).format(date);
}

function resolveThemeState(settings) {
    const preference = readThemePreference();
    const domMode =
        typeof document !== "undefined"
            ? document.documentElement.getAttribute("data-admin-theme-mode")
            : null;
    const domPalette =
        typeof document !== "undefined"
            ? document.documentElement.getAttribute("data-admin-theme-palette")
            : null;
    const mode = normalizeMode(
        preference.mode ?? settings.admin_theme_mode ?? domMode ?? "dark",
    );
    const lightPalette = normalizeLightPalette(
        preference.lightPalette ??
            settings.admin_light_palette ??
            (mode === "light" ? domPalette : null) ??
            "slate",
    );
    const darkPalette = normalizeDarkPalette(
        preference.darkPalette ??
            settings.admin_dark_palette ??
            (mode === "dark" ? domPalette : null) ??
            "midnight",
    );

    return {
        mode,
        lightPalette,
        darkPalette,
        palette: mode === "dark" ? darkPalette : lightPalette,
    };
}

function applyResolvedAdminTheme(themeState) {
    if (typeof document === "undefined") {
        return;
    }

    const root = document.documentElement;

    root.setAttribute("data-admin-theme-mode", themeState.mode);
    root.setAttribute("data-admin-theme-palette", themeState.palette);
}

function readThemePreference() {
    if (typeof window === "undefined") {
        return {};
    }

    try {
        const stored = window.localStorage.getItem(ADMIN_THEME_STORAGE_KEY);

        if (!stored) {
            return {};
        }

        const parsed = JSON.parse(stored);

        return parsed && typeof parsed === "object" ? parsed : {};
    } catch (error) {
        return {};
    }
}

function writeThemePreference(themeState) {
    if (typeof window === "undefined") {
        return;
    }

    window.localStorage.setItem(
        ADMIN_THEME_STORAGE_KEY,
        JSON.stringify({
            mode: themeState.mode,
            lightPalette: themeState.lightPalette,
            darkPalette: themeState.darkPalette,
        }),
    );
}

function normalizeMode(mode) {
    const value = String(mode ?? "")
        .trim()
        .toLowerCase();

    return themeModes.has(value) ? value : "dark";
}

function normalizeLightPalette(palette) {
    const value = String(palette ?? "")
        .trim()
        .toLowerCase();

    return lightPalettes.has(value) ? value : "slate";
}

function normalizeDarkPalette(palette) {
    const value = String(palette ?? "")
        .trim()
        .toLowerCase();

    return darkPalettes.has(value) ? value : "midnight";
}

function fallbackSettingsPayload() {
    return {
        settings: {
            site_name: "My CMS",
            admin_theme_mode: "dark",
            admin_light_palette: "slate",
            admin_dark_palette: "midnight",
        },
        options: {},
    };
}
