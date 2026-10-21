# Aircraft icons

The SVG aircraft icons in this folder are from [VATSIM Radar](https://github.com/VATSIM-Radar/vatsim-radar)
(`app/assets/icons/aircraft/`), licensed under
[Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)](https://creativecommons.org/licenses/by-nc/4.0/).

- The files are unmodified copies, renamed to their uppercase ICAO code (e.g. `b738.svg` → `B738.svg`).
- The OzBays map draws them as single-colour silhouettes tinted in the arrival airport's colour.
- They may only be used non-commercially, with credit to VATSIM Radar. The map credits them in its info panel.

Which icon each aircraft size group uses is set by the `AllocationIcon_<group>` keys in `public/config/aircraft.json`.
To use a different icon, copy its SVG from the VATSIM Radar repo into this folder as `<ICAO>.svg` and update that key.
