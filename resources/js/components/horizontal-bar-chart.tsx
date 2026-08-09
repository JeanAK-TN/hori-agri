import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { Bar } from 'react-chartjs-2';
import { useChartColors } from '@/hooks/use-chart-colors';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

type Props = {
    labels: string[];
    values: number[];
    /** Unité affichée dans l'infobulle, par exemple « kg/ha ». */
    unit?: string;
    /**
     * Une couleur par barre, pour une échelle ordonnée. Sans elle,
     * toutes les barres portent la même teinte : une série unique ne se
     * code pas par la couleur, l'axe la nomme déjà.
     */
    colors?: string[];
    ariaLabel: string;
};

const FRENCH = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

/**
 * Barres horizontales : les libellés de culture et de village sont longs,
 * et à l'horizontale ils se lisent sans incliner la tête ni le téléphone.
 */
export default function HorizontalBarChart({
    labels,
    values,
    unit,
    colors,
    ariaLabel,
}: Props) {
    const theme = useChartColors();

    // Une hauteur proportionnelle au nombre de barres : elles gardent la
    // même épaisseur qu'il y en ait deux ou dix.
    const height = Math.max(140, labels.length * 44 + 32);

    return (
        <div style={{ height }} role="img" aria-label={ariaLabel}>
            <Bar
                data={{
                    labels,
                    datasets: [
                        {
                            data: values,
                            backgroundColor: colors ?? theme['--chart-1'],
                            borderRadius: 4,
                            borderSkipped: false,
                            barThickness: 20,
                        },
                    ],
                }}
                options={{
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) =>
                                    `${FRENCH.format(context.parsed.x ?? 0)}${unit ? ` ${unit}` : ''}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: { color: theme['--border'] },
                            ticks: {
                                color: theme['--muted-foreground'],
                                callback: (value) =>
                                    FRENCH.format(Number(value)),
                            },
                        },
                        y: {
                            border: { display: false },
                            grid: { display: false },
                            ticks: { color: theme['--muted-foreground'] },
                        },
                    },
                }}
            />
        </div>
    );
}
