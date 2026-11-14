# Gastown Simulator: return flight, Landing elevator and Water Street heritage

The first SUZYFLY starts flight. A second SUZYFLY now navigates toward a
collision-tested destination in the central aisle of Station East Lot 531
and lowers Suzy smoothly before landing. Its L no longer gets consumed
by the separate LETSGO spell. No mid-air snap to street or rail yard.

The Landing's floor-six gallery now has a marked return elevator. Walking
into it rides back down to the ground floor, after which Suzy can walk
through the open lobby glass door and return to Water Street. KK appears
outside again, with a short guard against immediately re-entering.

On Water Street, real mapped civic building wall edges now receive varied
heritage-oriented shopfront columns, two-over-two sash, masonry lintels,
cornice brackets and parapet treatments. The renderer keeps the low-rise
sawtooth profile. No unlocated free-standing walls or towers are created.

Architectural style research:
- Edward Hotel (302 Water): four-storey stone-clad Edwardian frontage
  https://www.historicplaces.ca/en/rep-reg/place-lieu.aspx?id=2547
- Taylor Building (310 Water): five-storey brick/stone Edwardian frontage
  https://www.historicplaces.ca/en/rep-reg/place-lieu.aspx?id=2549
- McLuckie Warehouse (353 Water): five-storey north-side brick warehouse
  with sandstone lintels and corbelled cornice
  https://www.heritagesitefinder.ca/location/353-water-st-vancouver-bc
- Holland Block (350 Water): triangular three-storey Italianate massing
  https://www.historicplaces.ca/en/rep-reg/place-lieu.aspx?id=2559
- City of Vancouver Gastown Design Guidelines on sawtooth rooflines
  https://guidelines.vancouver.ca/guidelines-ha-2-gastown.pdf

Footprint IDs are NOT yet individually verified against these addresses,
so the details are typology-inspired, not claims that each polygon is
a specific named building. The existing GIS world, collision map,
parking geometry and station models are unchanged.

Next live QA: fly from a high altitude, spell SUZYFLY again and verify
a gradual safe lot touchdown; follow KK upstairs, take the return
elevator downstairs, walk out through glass doors and find KK again;
then walk east to the Steam Clock, checking roof heights, windows,
streetwall alignment and FPS in all camera modes.
