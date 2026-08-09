import { useMemo } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import type { ResolvedAppearance } from '@/hooks/use-appearance';

const TOKENS = [
    '--chart-1',
    '--chart-2',
    '--chart-3',
    '--chart-4',
    '--chart-5',
    '--chart-severity-1',
    '--chart-severity-2',
    '--chart-severity-3',
    '--muted-foreground',
    '--border',
] as const;

type Token = (typeof TOKENS)[number];
export type ChartColors = Record<Token, string>;

/**
 * Les mêmes valeurs que app.css, pour le rendu serveur et pour le cas où
 * un jeton manquerait. Elles ne sont pas une seconde source de vérité :
 * dès que le navigateur a la main, les jetons du thème les remplacent.
 */
const FALLBACK: Record<ResolvedAppearance, ChartColors> = {
    light: {
        '--chart-1': '#2a6b2c',
        '--chart-2': '#feb300',
        '--chart-3': '#a70515',
        '--chart-4': '#467c8a',
        '--chart-5': '#91d78a',
        '--chart-severity-1': '#8c0d16',
        '--chart-severity-2': '#c5423a',
        '--chart-severity-3': '#dd8279',
        '--muted-foreground': '#5a6157',
        '--border': '#c0c9bb',
    },
    dark: {
        '--chart-1': '#91d78a',
        '--chart-2': '#ffba38',
        '--chart-3': '#ffb3ac',
        '--chart-4': '#7fb2c2',
        '--chart-5': '#2a6b2c',
        '--chart-severity-1': '#ff8f85',
        '--chart-severity-2': '#e2685e',
        '--chart-severity-3': '#b03e35',
        '--muted-foreground': '#a3ada0',
        '--border': '#2c332a',
    },
};

function read(appearance: ResolvedAppearance): ChartColors {
    const fallback = FALLBACK[appearance];

    if (typeof window === 'undefined') {
        return fallback;
    }

    const style = getComputedStyle(document.documentElement);

    return TOKENS.reduce((colors, token) => {
        const value = style.getPropertyValue(token).trim();

        return { ...colors, [token]: value === '' ? fallback[token] : value };
    }, {} as ChartColors);
}

/**
 * Chart.js veut des couleurs, pas des variables CSS. On lit donc les
 * jetons du thème au moment du rendu, et on les relit quand l'apparence
 * change : les graphiques restent branchés sur le design system plutôt
 * que de recopier des valeurs qui divergeraient.
 */
export function useChartColors(): ChartColors {
    const { resolvedAppearance } = useAppearance();

    return useMemo(() => read(resolvedAppearance), [resolvedAppearance]);
}
