# Water Street westbound traffic and curb furniture (November 2026)

## Reference check

- The City of Vancouver's **2026 traffic circulation map** shows through-vehicle travel **westbound** on Water Street during ordinary weekday/Saturday operation, with the **eastbound lane intended for bicycles**, and a separate Sundays-only summer pedestrian zone. The 2026 summer pilot ended September 6, 2026. For the simulator's November baseline, there is no permanent all-day car closure.
  - https://vancouver.ca/files/cov/gastown-summer-traffic-circulation-map-2026.pdf
  - https://vancouver.ca/streets-transportation/gastown-public-spaces.aspx
- The 2026 approved Gastown Public Spaces Plan proposes *future* pedestrian-friendly transformations. Do not interpret future concept images as the current road configuration:
  - https://vancouver.ca/news-calendar/council-approves-people-first-vision-gastown-april-2026.aspx
- For Steam Clock curb treatment, refer to street photos showing chain bollards around the clock's sidewalk, not across the full road: https://www.earthtrekkers.com/best-things-to-do-in-vancouver/ .

## Root cause and fix

The procedural `createWaterStreetDetails` street frame uses `rotation.y=Math.atan2(-dx,-dz)` so **local -Z is along the street** and **local X is lateral**. Old furniture used `z=side*7.35` as its lateral offset and `x=n*5.1` as its along-street spacing, unintentionally drawing the chain rows, lamp posts, and trees in the road. This was especially visible in the screenshots of Water Street near The Landing and the Steam Clock.

- Re-anchor trees, benches, five-globe heritage lampposts, bike racks, and signs beyond the 10.4 m model roadway, on ±X sidewalk positions. Keep all static fixture bases at least 0.75 m outside the model pavement edge.
- Model just one short group of north-side decorative chains near the Steam Clock. Do not repeat chain barriers on every Water Street block.
- Align the curbside delivery van to the westbound direction.
- Create a separate Water Street traffic path along committed GIS route indices 7..16. Vehicles spawn near Water/Cambie and drive only west toward the Waterfront end, on the north/right-hand motor lane. This is a deterministic ambience loop, not a live traffic feed.
- Preserve the red-brick street-paver styling and existing heritage-frontage footprints; this PR is **not** a map or architecture rebuild.

## Manual WebGL QA after deployment

1. Hard-refresh `/gastown-sim/`. At Water Street in third-person view, look **east toward Steam Clock**. The full roadway must be traversable visually, without chain rails, lamp poles, tree trunks, or benches in the driving lane.
2. Observe several cars arrive from the eastern end and travel **west**. None should head east. Cars should pass the stationary curb delivery van with space to spare.
3. Walk toward the Steam Clock. A **short, north-side chain** may appear by the clock, but it must not cross the road.
4. Inspect from the opposite direction too. Check roadside furniture, facades, curb edges, third-person camera, and existing Harbour Centre stop and bus motion.
5. Run `node --test __tests__/gastown-water-street-heritage.test.js __tests__/gastown-film-scene-v11.test.js __tests__/gastown-street-life.test.js` before merging.

**Fidelity caveat:** Building footprints are 2015 City GIS and road/sidewalk widths are stylized. The scene is not safe for navigation or a live regulation checker.
