---
name: MancWay Precision
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#45464d'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#855300'
  on-secondary: '#ffffff'
  secondary-container: '#fea619'
  on-secondary-container: '#684000'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#271901'
  on-tertiary-container: '#98805d'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#ffddb8'
  secondary-fixed-dim: '#ffb95f'
  on-secondary-fixed: '#2a1700'
  on-secondary-fixed-variant: '#653e00'
  tertiary-fixed: '#fcdeb5'
  tertiary-fixed-dim: '#dec29a'
  on-tertiary-fixed: '#271901'
  on-tertiary-fixed-variant: '#574425'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
  status-confirmed: '#10B981'
  status-provisional: '#F97316'
  status-in-progress: '#3B82F6'
  status-completed: '#8B5CF6'
  status-conflict: '#EF4444'
  status-cancelled: '#94A3B8'
  surface-steel: '#F1F5F9'
  surface-charcoal: '#1E293B'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  data-mono:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  grid-margin: 24px
  gutter: 16px
  density-compact: 8px
  density-comfortable: 16px
---

## Brand & Style
The design system is built for the high-stakes, fast-moving environment of vehicle recovery. The brand personality is **authoritative, dependable, and ultra-efficient**. It targets dispatchers, fleet managers, and operators who require immediate clarity under pressure.

The aesthetic follows a **Modern Industrial** style: 
- **Efficiency over Embellishment:** Whitespace is used strategically to group related data, but density is prioritized to minimize scrolling.
- **High Functional Contrast:** Clear distinction between background surfaces and interactive elements to ensure legibility in various lighting conditions (including mobile use in recovery vehicles).
- **Structured Rigidity:** A strict adherence to grid systems and clean lines conveys a sense of logistical order and reliability.

## Colors
This design system utilizes a high-contrast palette designed for functional hierarchy:

- **Primary (Deep Navy):** Used for navigation, headers, and primary actions to establish a professional, grounded foundation.
- **Secondary (Safety Amber):** Reserved for alerts, critical highlights, and active "Attention Required" states.
- **Neutral Scale:** Uses "Steel-grey" for backgrounds and borders to reduce eye strain, and "Charcoal" for high-contrast text.
- **Semantic Palette:** A robust set of colors for recovery job statuses. These must be used consistently across grids, calendars, and badges to allow for "at-a-glance" fleet monitoring.

## Typography
The typography system is optimized for **data density**. 

- **Inter** is the primary typeface, chosen for its exceptional legibility in small sizes and its neutral, professional tone.
- **JetBrains Mono** is introduced for specific data-heavy strings, such as Vehicle Identification Numbers (VINs), Registration Plates, and Geo-coordinates, where character distinction is critical.
- **Micro-copy:** Use `label-caps` for table headers and section dividers to create clear hierarchy without taking up vertical space.

## Layout & Spacing
The layout uses a **12-column fluid grid** for main dashboards, but shifts to a **Resource-Lane** model for dispatch calendars.

- **Spacing Rhythm:** Based on a 4px baseline grid. 
- **Density Toggles:** The UI should support a "Compact" view for expert users (8px padding in cells/cards) and a "Comfortable" view for mobile or standard use (16px padding).
- **Data Grids:** Use 16px horizontal gutters between columns to ensure clear separation of data points, with 1px borders between rows to maintain horizontal scanning paths.

## Elevation & Depth
In this design system, depth is used sparingly to maintain an industrial, "flat-plus" feel.

- **Tonal Layers:** We use three primary surface levels:
  1. **Background (Steel-grey):** The base level for the entire application.
  2. **Cards (White):** Elevated slightly to house specific recovery job details or data blocks.
  3. **Modals/Overlays:** Highest level, using a crisp 1px border (`#E2E8F0`) and a tight, low-blur shadow to indicate focus.
- **Focus States:** Use a 2px outer glow in Safety Amber for active input fields or selected resource lanes to ensure the operator's focus is never lost.

## Shapes
The shape language is **Soft (0.25rem)**. 

Sharp corners are avoided to prevent the UI from feeling overly aggressive, but the radius is kept minimal to maximize the internal area for data display. 
- **Status Badges:** Use the `rounded-xl` setting (0.75rem) to create a "pill" shape, making them instantly distinguishable from square data fields.
- **Interactive Elements:** Buttons and Inputs follow the standard `0.25rem` radius for a disciplined, industrial appearance.

## Components
- **Buttons:** Primary buttons use the Deep Navy background with white text. Secondary buttons use a ghost style (Steel-grey border). All buttons have a high-contrast hover state.
- **Data Grids:** Headers must be "sticky." Include a "Status Column" where the text color matches the semantic palette defined in Section 2.
- **Resource Cards:** Small, information-dense cards used in the calendar view. Should include: Reg Number (Mono font), Driver Name, and a color-coded left border indicating the current job status.
- **Input Fields:** Use a "Label-In-Border" or top-aligned label style to minimize horizontal width. Required fields are marked with a Safety Amber asterisk.
- **Chips/Badges:** Used for vehicle types (e.g., "Flatbed," "Spec-Lift"). These should use a light grey background with Charcoal text to avoid clashing with the high-priority Semantic Status badges.