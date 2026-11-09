# Waterfront screenshot fidelity audit | November 9, 2026

## Ground truth and limitations

The supplied Google Maps 3D and Street View captures show the Station East parking deck, the east-side station doorway, Harbour Centre, the Spencer/Harbour Centre podium, and the surrounding Cordova/Hastings street walls. These captures are *visual references*, not redistributable textures or a measured 3D survey.

- Harbour Centre: **555 W Hastings Street**, 28 office floors, landmark observation deck / revolving restaurant crown; completed 1977. Sources: https://harbourcentre.com/about/ and https://www.skyscrapercenter.com/building/harbour-centre/4144
- Station East surface lot: **555 W Cordova Street**, Impark Lot **531**, official northbound entrance from West Cordova just west of Richards, with 76 spaces, weight and height limits and harbour views. Source: https://lots.impark.com/IMP/en/details/1/531?csr=0
- The screenshot of the station's eastern entrance shows **tall dark-green glazed doors in a red-brick wall** opening toward the parking deck, with a transom above. The current mesh places the threshold at local x=55.1 and has an arcade into the concourse. Its *side/orientation* needs a geometry and camera audit before repositioning, otherwise the portal and collision mesh may diverge.

## Shipped in this focused PR

- **M** toggles the in-game side menu and releases pointer lock. The existing sidebar click/pointerdown and Escape affordances remain. Input focus in a form is respected.
- Parking-lot NPC speech snippets now draw from the actual parking-review themes provided in the project conversation: narrow stalls, ticket disputes, online payment, vehicle restrictions and, wonderfully, the North Shore view. The lines are condensed/adapted and **not attributed to individual reviewers**. Other neighbourhood NPCs retain their distinct dialogue.

## Visual alignment still required

- Identify Harbour Centre's *actual* source footprint in the local GIS data before assigning the crown and 28-storey shaft. Do not rename the existing generic Seymour tower as Harbour Centre without verifying parcel coordinates. Verify its tower's placement north of Hastings, Spencer's lower streetwall along Cordova, and the cylindrical/round lookout above its roof. Distinguish the adjacent Sinclair Centre, Waterfront Centre and newer glass blocks.
- Match the photographed **east-facing** Station East portal to the actual side of the model. Move entrance geometry, world-space interaction threshold, walking bounds, and camera collision together. Test walking in from the lot and back into the concourse.
- Screenshot-based QA: compare the parking deck view toward Harbour Centre and the Seymour/Hastings view against the supplied map and street-view captures at matching camera bearings. Run WebGL manual acceptance tests, including menu navigation under pointer lock.

The existing generated skyline is approximate. This PR deliberately avoids claiming architecture accuracy that has not yet been confirmed in a rendered test.
