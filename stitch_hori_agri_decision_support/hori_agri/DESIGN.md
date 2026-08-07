---
name: Hori Agri
colors:
  surface: '#f3faff'
  surface-dim: '#c7dde9'
  surface-bright: '#f3faff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#e6f6ff'
  surface-container: '#dbf1fe'
  surface-container-high: '#d5ecf8'
  surface-container-highest: '#cfe6f2'
  on-surface: '#071e27'
  on-surface-variant: '#41493e'
  inverse-surface: '#1e333c'
  inverse-on-surface: '#dff4ff'
  outline: '#717a6d'
  outline-variant: '#c0c9bb'
  surface-tint: '#2a6b2c'
  primary: '#00450d'
  on-primary: '#ffffff'
  primary-container: '#1b5e20'
  on-primary-container: '#90d689'
  inverse-primary: '#91d78a'
  secondary: '#7e5700'
  on-secondary: '#ffffff'
  secondary-container: '#feb300'
  on-secondary-container: '#6a4800'
  tertiary: '#7c000b'
  on-tertiary: '#ffffff'
  tertiary-container: '#a70515'
  on-tertiary-container: '#ffb2aa'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#acf4a4'
  primary-fixed-dim: '#91d78a'
  on-primary-fixed: '#002203'
  on-primary-fixed-variant: '#0c5216'
  secondary-fixed: '#ffdeac'
  secondary-fixed-dim: '#ffba38'
  on-secondary-fixed: '#281900'
  on-secondary-fixed-variant: '#604100'
  tertiary-fixed: '#ffdad6'
  tertiary-fixed-dim: '#ffb3ac'
  on-tertiary-fixed: '#410003'
  on-tertiary-fixed-variant: '#930010'
  background: '#f3faff'
  on-background: '#071e27'
  surface-variant: '#cfe6f2'
typography:
  headline-lg:
    fontFamily: Work Sans
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Work Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Work Sans
    fontSize: 24px
    fontWeight: '500'
    lineHeight: 32px
  body-lg:
    fontFamily: Source Sans 3
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Source Sans 3
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Source Sans 3
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  data-mono:
    fontFamily: Source Sans 3
    fontSize: 14px
    fontWeight: '700'
    lineHeight: 20px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  container-margin: 24px
  gutter: 16px
  card-padding: 20px
---

## Brand & Style
The brand personality is grounded, reliable, and authoritative, specifically tailored for the West African agricultural landscape (Togo). The design narrative centers on **"Scientific Clarity,"** blending a professional, data-driven SaaS aesthetic with organic, agricultural sensibilities. 

The design style is **Corporate / Modern** with a high emphasis on legibility and accessibility. It utilizes structured layouts to convey trust and precision, ensuring that complex agronomic data—"Aide à la décision agronomique"—is digestible for field agents and agricultural supervisors. The UI avoids unnecessary decorative elements, favoring a functional, clean interface that prioritizes actionable insights.

## Colors
The palette is optimized for clarity and high visibility in outdoor or high-glare environments. 

- **Primary (Deep Forest):** Used for core navigation and "Faible" (Low) severity indicators. This green is darkened to ensure WCAG AA compliance when paired with white text.
- **Secondary (Amber):** Used for "Moyenne" (Medium) severity. It provides a distinct contrast against both green and white.
- **Tertiary (Crimson):** Reserved for "Élevée" (High) severity and critical alerts. 
- **Neutral (Slate):** Used for typography and secondary UI elements to maintain a professional tone without the harshness of pure black.

**Accessibility Rule:** To avoid green-on-green or red-on-red legibility issues, all status labels must use a "Tonal Container" approach: a very desaturated, light background (90% lightness) with a high-contrast, dark-toned foreground text (e.g., Dark Red text on a Pale Pink background).

## Typography
The typography system prioritizes utilitarian legibility. **Work Sans** provides a sturdy, professional foundation for headings, while **Source Sans 3** offers a neutral, highly readable experience for long-form agronomic recommendations.

Special attention is paid to numerical data. Seeding densities (e.g., "50,000 graines/ha") and dates (JJ/MM/AAAA) should utilize slightly heavier weights to ensure they stand out as the primary "data points" within the body text.

## Layout & Spacing
The design system employs a **Fluid Grid** with a baseline 8px rhythm. 

- **Desktop:** 12-column grid with 24px margins.
- **Mobile:** 4-column grid with 16px margins.

The layout is designed for density; information regarding Maize, Soy, and Cassava (Manioc) crops is often technical, requiring side-by-side comparisons of technical itineraries. Use "Safe Areas" for mobile interactions to ensure buttons are easily tappable for field users.

## Elevation & Depth
The system uses **Tonal Layers** rather than heavy shadows to define hierarchy. 
- **Level 0 (Background):** Light gray (#F8F9FA) to reduce eye strain.
- **Level 1 (Cards/Containers):** Pure white with a 1px low-contrast outline (#E0E0E0).
- **Level 2 (Dropdowns/Modals):** Subtle ambient shadow (0px 4px 12px rgba(0,0,0,0.05)) to suggest interaction.

This flat, layered approach ensures the UI remains performant on mid-range mobile devices common in the Togolese market.

## Shapes
A **Soft** (Level 1) roundedness is applied throughout the system. This provides a modern feel while maintaining the professional rigor of an industrial/agricultural tool. 
- Buttons and input fields: 0.25rem (4px).
- Main container cards: 0.5rem (8px).
- Crop selection chips: 1rem (Full pill) to distinguish them as selectable filter elements.

## Components
- **Severity Badges:** Must use semantic colors. "Élevée" (High) is Red, "Moyenne" (Medium) is Amber, and "Faible" (Low) is Green. Text must be high-contrast (dark on light).
- **Navigation:** A persistent sidebar (desktop) or bottom-bar (mobile) with five core nodes: *Tableau de bord, Producteurs, Recommandations, Itinéraires, Paramètres*.
- **Data Inputs:** Specific density inputs must include the suffix "graines/ha" within the field focus state.
- **Crop Chips:** Distinctive toggles for *Maïs, Soja,* and *Manioc* using icons combined with text.
- **Cards:** Used for producer profiles and itinerary summaries. Cards should feature a 4px left-border accent colored by the severity status of the latest recommendation.
- **Date Picker:** Must strictly follow the `JJ/MM/AAAA` format to align with local regional standards.