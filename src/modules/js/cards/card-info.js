// 自動生成: scripts/build-card-info.mjs（npm run build:card-info）。編集しない。元は docs/specs/002-card-data.md（英語名・数値・英語の効果文）。
export const CARD_INFO = {
 "projects": {
  "P001": {
   "name": "Aviation Engineer",
   "type": "CREW",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PLANNING_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Planning Phase \"Retrieve Action Cards\" step, if your upright Action Card is \"II Deploy\" or \"III Launch/Turn\", return it to your hand. That is, you can choose \"II Deploy\" or \"III Launch/Turn\" in consecutive rounds."
  },
  "P002": {
   "name": "Aviation Commander",
   "type": "CREW",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PLANNING_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Planning Phase \"Retrieve Action Cards\" step, if your upright Action Card is \"IV Establish Link\" or \"V Proposal\", return it to your hand. That is, you can choose \"IV Establish Link\" or \"V Proposal\" in consecutive rounds."
  },
  "P003": {
   "name": "Lonesome Rabbit",
   "type": "CREW",
   "cost": 6,
   "fame": 1,
   "fuelSlots": 3,
   "timing": "PLANNING_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "If your action choice does not overlap with that of any other player, +2 [Maya]."
  },
  "P004": {
   "name": "Course Corrector",
   "type": "CREW",
   "cost": 3,
   "fame": 1,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ANY",
   "text": "During the Budget Phase, you may pay 2 [Maya]. If you do, choose one of your satellites, spend 1 [Fuel], and move it forward 1 area."
  },
  "P005": {
   "name": "Procurement Officer",
   "type": "CREW",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Budget Phase, you may pay 4 [Maya]. If you do, draw 1 Project card."
  },
  "P006": {
   "name": "Budget Negotiator",
   "type": "CREW",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "At the start of the Budget Phase, you may pay 5 [Maya]. If you do, +1 [Budget]."
  },
  "P007": {
   "name": "Master Negotiator",
   "type": "CREW",
   "cost": 9,
   "fame": 2,
   "fuelSlots": 4,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Budget Phase, when you gain an Action Selection Bonus, +2 [Maya]."
  },
  "P008": {
   "name": "Integrated Operator",
   "type": "CREW",
   "cost": 10,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "For each of your satellites that has [CREW], [DEVICE] and [SOFTWARE] all deployed, the [Maya] you can gain in the Budget Phase is +2."
  },
  "P009": {
   "name": "First Penguin",
   "type": "CREW",
   "cost": 3,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ON_DEPLOY",
   "active": "ANY",
   "text": "When this card is deployed, draw 1 Project card."
  },
  "P010": {
   "name": "Ground Mechanic",
   "type": "CREW",
   "cost": 4,
   "fame": 1,
   "fuelSlots": 1,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ANY",
   "text": "Deployment cost for this satellite -2."
  },
  "P011": {
   "name": "Twin Deploy Technician",
   "type": "CREW",
   "cost": 4,
   "fame": 1,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [DEVICE] card is deployed to this satellite, you may pay its deployment cost to deploy 1 [DEVICE] card to any satellite of yours."
  },
  "P012": {
   "name": "Docking Mechanic",
   "type": "CREW",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost for this satellite -3."
  },
  "P013": {
   "name": "Crew Commander",
   "type": "CREW",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [CREW] is deployed to this satellite, +1 [Budget]."
  },
  "P014": {
   "name": "Crew Coordinator",
   "type": "CREW",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ANY",
   "text": "Deployment cost of [CREW] cards deployed to this satellite -3."
  },
  "P015": {
   "name": "Device Installer",
   "type": "CREW",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ANY",
   "text": "Deployment cost of [DEVICE] cards deployed to this satellite -3."
  },
  "P016": {
   "name": "System Architect",
   "type": "CREW",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ANY",
   "text": "Deployment cost of [SOFTWARE] cards deployed to this satellite -3."
  },
  "P017": {
   "name": "Chief Device Officer",
   "type": "CREW",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost of [DEVICE] deployed to this satellite -4."
  },
  "P018": {
   "name": "Squadron Aide",
   "type": "CREW",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost for all your satellites -1."
  },
  "P019": {
   "name": "Fleet Commander",
   "type": "CREW",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost of [CREW] for all your satellites -2."
  },
  "P020": {
   "name": "Fleet Engineer",
   "type": "CREW",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost of [DEVICE] for all your satellites -2."
  },
  "P021": {
   "name": "Fleet Architect",
   "type": "CREW",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost of [SOFTWARE] for all your satellites -2."
  },
  "P022": {
   "name": "Juggler",
   "type": "CREW",
   "cost": 8,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ON_DEPLOY",
   "active": "ANY",
   "text": "When this card is deployed, rearrange all Project cards on your standby (inactive) satellites as you like. However, \"On Deploy\" effects are not triggered."
  },
  "P023": {
   "name": "Integrated Planner",
   "type": "CREW",
   "cost": 9,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ANY",
   "text": "When a Project card is deployed to this satellite, draw 1 Project card."
  },
  "P024": {
   "name": "Swap Technician",
   "type": "CREW",
   "cost": 10,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ON_DEPLOY",
   "active": "ANY",
   "text": "When this card is deployed, you may choose one of your active satellites. If you do, swap its active state, fuel consumption and location with this satellite."
  },
  "P025": {
   "name": "Pioneering Gorilla",
   "type": "CREW",
   "cost": 10,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [SOFTWARE] is deployed to this satellite, draw 2 Project cards."
  },
  "P026": {
   "name": "French Bulldog",
   "type": "CREW",
   "cost": 10,
   "fame": 6,
   "fuelSlots": 4,
   "timing": "DEPLOYED",
   "kind": "ON_DEPLOY",
   "active": "ANY",
   "text": "When deploying this card, deployment cost -2 for each card already on the target satellite."
  },
  "P027": {
   "name": "Personnel Planner",
   "type": "CREW",
   "cost": 11,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [CREW] is deployed to any of your satellites, draw 1 Project card."
  },
  "P028": {
   "name": "Equipment Planner",
   "type": "CREW",
   "cost": 11,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [DEVICE] is deployed to any of your satellites, draw 1 Project card."
  },
  "P029": {
   "name": "IT Planner",
   "type": "CREW",
   "cost": 11,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [SOFTWARE] is deployed to any of your satellites, draw 1 Project card."
  },
  "P030": {
   "name": "Talent Development Officer",
   "type": "CREW",
   "cost": 12,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [CREW] is deployed to any of your satellites, [Budget] +1."
  },
  "P031": {
   "name": "Equipment Investment Officer",
   "type": "CREW",
   "cost": 12,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [DEVICE] is deployed to any of your satellites, [Budget] +1."
  },
  "P032": {
   "name": "IT Investment Officer",
   "type": "CREW",
   "cost": 12,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When a [SOFTWARE] is deployed to any of your satellites, [Budget] +1."
  },
  "P033": {
   "name": "Seventh Prince",
   "type": "CREW",
   "cost": 8,
   "fame": 6,
   "fuelSlots": 3,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ANY",
   "text": "Project cards cannot be deployed to this satellite."
  },
  "P034": {
   "name": "Acceleration Booster",
   "type": "DEVICE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When this satellite is launched, you may pay 2/3/4/5 [Maya]. If you do, move this satellite forward 1/2/3/4 areas and spend 1/2/3/4 [Fuel]. No near-miss check is made for the areas passed through, but the landing/splashdown check is made."
  },
  "P035": {
   "name": "Gamble System",
   "type": "DEVICE",
   "cost": 7,
   "fame": 0,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When this satellite is launched, discard this card. Instead, deploy the top card of the Project deck to this satellite without paying its deployment cost. The newly deployed card's \"On Deploy\" and \"On Launch\" effects are triggered."
  },
  "P036": {
   "name": "Lightweight Frame",
   "type": "DEVICE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ACTIVATED",
   "active": "ANY",
   "text": "When you launch a satellite, launch cost -1 for each card deployed to that satellite."
  },
  "P037": {
   "name": "Attitude Controller",
   "type": "DEVICE",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 3,
   "timing": "TURNED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite turns, draw 1 Project card."
  },
  "P038": {
   "name": "Fuel Transfer Pipe",
   "type": "DEVICE",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 3,
   "timing": "LAUNCHED",
   "kind": "ACTIVATED",
   "active": "ANY",
   "text": "When you launch a satellite, you may reduce [Fuel] by any amount up to that satellite's loaded fuel. If you do, choose another of your active satellites and recover its fuel by the amount reduced. However, it cannot recover beyond its initial fuel slot."
  },
  "P039": {
   "name": "Sensor Array",
   "type": "DEVICE",
   "cost": 9,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When this satellite is launched, draw Project cards equal to the number of [DEVICE] cards deployed to this satellite."
  },
  "P040": {
   "name": "Launch Assist Unit",
   "type": "DEVICE",
   "cost": 9,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LAUNCHED",
   "kind": "CONSTANT",
   "active": "ANY",
   "text": "Launch cost of your satellites -3."
  },
  "P041": {
   "name": "Launch Contract Unit",
   "type": "DEVICE",
   "cost": 12,
   "fame": 1,
   "fuelSlots": 3,
   "timing": "LAUNCHED",
   "kind": "ACTIVATED",
   "active": "ANY",
   "text": "When you launch a satellite, +1 [Budget]."
  },
  "P042": {
   "name": "Dancing Dog Robot",
   "type": "DEVICE",
   "cost": 15,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When this satellite is launched, [Budget] +1 for each [DEVICE] card deployed to this satellite."
  },
  "P043": {
   "name": "Regenerative Thruster",
   "type": "DEVICE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "TURNED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite turns, recover 1 [Fuel]."
  },
  "P044": {
   "name": "Formation Sync Unit",
   "type": "DEVICE",
   "cost": 6,
   "fame": 3,
   "fuelSlots": 3,
   "timing": "TURNED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When another of your satellites turns, this satellite may turn."
  },
  "P045": {
   "name": "Orbital Repair Dock",
   "type": "DEVICE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "TURNED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite turns, you may pay deployment cost to deploy 1 Project card to this satellite."
  },
  "P046": {
   "name": "Fixed Orbit Unit",
   "type": "DEVICE",
   "cost": 7,
   "fame": 5,
   "fuelSlots": 4,
   "timing": "TURNED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "This satellite cannot turn."
  },
  "P047": {
   "name": "Tornado Bit",
   "type": "DEVICE",
   "cost": 9,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "TURNED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite turns, you may pay the special link cost to establish a link."
  },
  "P048": {
   "name": "Variable Orbit Unit",
   "type": "DEVICE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, it may turn."
  },
  "P049": {
   "name": "Solar Panel",
   "type": "DEVICE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Each time this satellite establishes a link, recover 1 [Fuel]."
  },
  "P050": {
   "name": "Euro-Africa Relay Network",
   "type": "DEVICE",
   "cost": 6,
   "fame": 1,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [A][C][E], you may additionally pay the special link cost. If you do, choose another of your satellites in region [A][C][E] and establish a link with it."
  },
  "P051": {
   "name": "Americas-Asia Relay Network",
   "type": "DEVICE",
   "cost": 6,
   "fame": 1,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [B][D][F], you may additionally pay the special link cost. If you do, choose another of your satellites in region [B][D][F] and establish a link with it."
  },
  "P052": {
   "name": "Continental Antenna",
   "type": "DEVICE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, adjacent [Land] areas are also added to the target areas."
  },
  "P053": {
   "name": "Starboard Antenna",
   "type": "DEVICE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, the 2 adjacent areas on the right side relative to its heading are also added to the target areas."
  },
  "P054": {
   "name": "Port Antenna",
   "type": "DEVICE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, the 2 adjacent areas on the left side relative to its heading are also added to the target areas."
  },
  "P055": {
   "name": "Stealth Ship",
   "type": "DEVICE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link to an area that another player has already linked, you do not pay the pioneer cost to that player."
  },
  "P056": {
   "name": "Forward Wide-Area Transmitter",
   "type": "DEVICE",
   "cost": 8,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, you may additionally pay the special link cost. If you do, additionally establish a link with the adjacent area ahead of this satellite's heading."
  },
  "P057": {
   "name": "Rear Wide-Area Transmitter",
   "type": "DEVICE",
   "cost": 8,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, you may additionally pay the special link cost. If you do, additionally establish a link with the adjacent area behind this satellite's heading."
  },
  "P058": {
   "name": "Crimson Remote Comm Unit",
   "type": "DEVICE",
   "cost": 8,
   "fame": 1,
   "fuelSlots": 3,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When another of your satellites establishes a link in region [C][E], you may pay the special link cost to have this satellite establish a link too."
  },
  "P059": {
   "name": "Lapis Remote Comm Unit",
   "type": "DEVICE",
   "cost": 8,
   "fame": 1,
   "fuelSlots": 3,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When another of your satellites establishes a link in region [D][F], you may pay the special link cost to have this satellite establish a link too."
  },
  "P060": {
   "name": "North Sea Waveform Relay",
   "type": "DEVICE",
   "cost": 8,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [A][G], you may pay the special link cost. If you do, choose 1 adjacent [Land] area and additionally establish a link there."
  },
  "P061": {
   "name": "South Russia Waveform Relay",
   "type": "DEVICE",
   "cost": 8,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [B][H], you may pay the special link cost. If you do, choose 1 adjacent [Land] area and additionally establish a link there."
  },
  "P062": {
   "name": "Dust Ocean Antenna",
   "type": "DEVICE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [E][G], adjacent [Sea] areas are added to the target areas."
  },
  "P063": {
   "name": "Chile Ocean Antenna",
   "type": "DEVICE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [F][H], adjacent [Sea] areas are added to the target areas."
  },
  "P064": {
   "name": "Forward Beam Antenna",
   "type": "DEVICE",
   "cost": 10,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, the 3 adjacent areas ahead of its heading are added to the target areas."
  },
  "P065": {
   "name": "Rear Beam Antenna",
   "type": "DEVICE",
   "cost": 10,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, the 3 adjacent areas behind its heading are added to the target areas."
  },
  "P066": {
   "name": "Signal Blocker",
   "type": "DEVICE",
   "cost": 10,
   "fame": 8,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "This satellite cannot establish a link."
  },
  "P067": {
   "name": "Churyumov",
   "type": "SOFTWARE",
   "cost": 3,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, the number of Project cards you draw increases by 1. However, the number you keep in hand does not change."
  },
  "P068": {
   "name": "Shannon Entropy",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When discarding Project cards in the Proposal Phase, you may discard cards other than those drawn during the Proposal Phase."
  },
  "P069": {
   "name": "Roscosmos",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, you may pay 4 [Maya]. If you do, +1 [Budget]."
  },
  "P070": {
   "name": "Spin-off",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, you may additionally discard 1 Project card. If you do, +7 [Maya]."
  },
  "P071": {
   "name": "Chandrasekhar",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "For the Proposal Phase Action Selection Bonus, both the number drawn and the number kept in hand increase by 1."
  },
  "P072": {
   "name": "Lanchester",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, you may pay 4/7/10 [Maya]. If you do, the number you keep in hand increases by 1/2/3."
  },
  "P073": {
   "name": "Tesla Memory",
   "type": "SOFTWARE",
   "cost": 8,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "PROPOSAL_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, the number you keep in hand increases by 1."
  },
  "P074": {
   "name": "Zero-Day Deploy",
   "type": "SOFTWARE",
   "cost": 9,
   "fame": 3,
   "fuelSlots": 3,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, you may discard 1 Project card. If you do, choose 1 Project card, pay its deployment cost and deploy it to any of your satellites."
  },
  "P075": {
   "name": "Faraday",
   "type": "SOFTWARE",
   "cost": 10,
   "fame": 1,
   "fuelSlots": 3,
   "timing": "PROPOSAL_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "When you draw Project cards in the Proposal Phase, +1 [Maya] for each card drawn."
  },
  "P076": {
   "name": "Ground Link",
   "type": "SOFTWARE",
   "cost": 10,
   "fame": 3,
   "fuelSlots": 3,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Proposal Phase, you may discard 2 Project cards. If you do, choose one of your satellites, pay the link cost, and establish a link with 1 of the [Land] areas adjacent to that satellite."
  },
  "P077": {
   "name": "Orion Reward",
   "type": "SOFTWARE",
   "cost": 7,
   "fame": 3,
   "fuelSlots": 2,
   "timing": "ANYTIME",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "At any time, the [Maya] you receive when you discard a Project card is +2."
  },
  "P078": {
   "name": "Apollo Down",
   "type": "SOFTWARE",
   "cost": 3,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When any of your satellites lands or splashes down, draw 1 Project card."
  },
  "P079": {
   "name": "Zero Gravity Alpha",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 1,
   "fuelSlots": 1,
   "timing": "ORBIT_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are all deployed to this satellite, this satellite does not spend [Fuel] while above region [A][H]."
  },
  "P080": {
   "name": "Zero Gravity Beta",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 1,
   "fuelSlots": 1,
   "timing": "ORBIT_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are all deployed to this satellite, this satellite does not spend [Fuel] while above region [B][E]."
  },
  "P081": {
   "name": "Hohmann Booster",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above an area you have linked, you may pay 2 [Maya]. If you do, move this satellite forward 1 area in its heading and spend 1 [Fuel]. However, no near-miss check is made, and the landing/splashdown check is made."
  },
  "P082": {
   "name": "Triad Booster",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "In the Orbit Phase, if [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, it may orbit 1 additional area. If you do, spend 1 [Fuel]; the near-miss check is made in the area moved to."
  },
  "P083": {
   "name": "Albedo Refill",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above region [A][C][H] from a different region, recover 2 [Fuel]."
  },
  "P084": {
   "name": "Geothermal Refill",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above region [D][F][H] from a different region, recover 2 [Fuel]."
  },
  "P085": {
   "name": "Kessler Avoidance System",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "The [Fuel] this satellite spends when it near-misses another satellite is 1 less. That is, recover 1 [Fuel] on a near miss."
  },
  "P086": {
   "name": "Data Downlink",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above an area you have linked, you may pay 3 [Maya]. If you do, draw 1 Project card."
  },
  "P087": {
   "name": "Data Rolling",
   "type": "SOFTWARE",
   "cost": 4,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "At the start of the Orbit Phase, draw 1 Project card. Then discard 1 Project card from your hand."
  },
  "P088": {
   "name": "Huygens Down",
   "type": "SOFTWARE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When any of your satellites lands or splashes down, +8 [Maya]."
  },
  "P089": {
   "name": "Landing Down",
   "type": "SOFTWARE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When any of your satellites lands on a [Land] area, +15 [Maya]."
  },
  "P090": {
   "name": "Gimbal Control",
   "type": "SOFTWARE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite moves between regions, it may turn."
  },
  "P091": {
   "name": "Cascade Boost",
   "type": "SOFTWARE",
   "cost": 5,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite moves between regions, you may move it 1 additional area. If you do, spend 1 [Fuel]; the near-miss check is made in the area moved to."
  },
  "P092": {
   "name": "Splashdown",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When any of your satellites splashes down on a [Sea] area, +10 [Maya]."
  },
  "P093": {
   "name": "Orbital 72",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above an area you have linked, +3 [Maya]."
  },
  "P094": {
   "name": "Deconflict",
   "type": "SOFTWARE",
   "cost": 6,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, this satellite does not near-miss. If there is 1 or fewer other satellite in that area, that satellite is also not considered to have near-missed."
  },
  "P095": {
   "name": "Flip Maneuver",
   "type": "SOFTWARE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above an area you have linked, it may turn."
  },
  "P096": {
   "name": "Alpha Link",
   "type": "SOFTWARE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above region [A][C][F] from a different region, you may pay the special link cost. If you do, this satellite establishes a link."
  },
  "P097": {
   "name": "Beta Link",
   "type": "SOFTWARE",
   "cost": 7,
   "fame": 2,
   "fuelSlots": 3,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above region [B][D][G] from a different region, you may pay the special link cost. If you do, this satellite establishes a link."
  },
  "P098": {
   "name": "Back Link",
   "type": "SOFTWARE",
   "cost": 9,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite enters the airspace above an area you have linked, you may pay the special link cost. If you do, establish a link with the area behind this satellite's heading."
  },
  "P099": {
   "name": "Flyby Link",
   "type": "SOFTWARE",
   "cost": 11,
   "fame": 2,
   "fuelSlots": 2,
   "timing": "ORBIT_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite moves between regions, you may pay the special link cost to establish a link."
  }
 },
 "satellites": {
  "R001B": {
   "shape": "HAMMER",
   "plan": "MOON",
   "color": "BLUE",
   "launchCost": 10,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 1 Project card."
  },
  "R001G": {
   "shape": "HAMMER",
   "plan": "MOON",
   "color": "GREEN",
   "launchCost": 10,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 1 Project card."
  },
  "R001R": {
   "shape": "HAMMER",
   "plan": "MOON",
   "color": "RED",
   "launchCost": 10,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 1 Project card."
  },
  "R001Y": {
   "shape": "HAMMER",
   "plan": "MOON",
   "color": "YELLOW",
   "launchCost": 10,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 1 Project card."
  },
  "R002B": {
   "shape": "RETRO",
   "plan": "MOON",
   "color": "BLUE",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +2 [Budget]."
  },
  "R002G": {
   "shape": "RETRO",
   "plan": "MOON",
   "color": "GREEN",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +2 [Budget]."
  },
  "R002R": {
   "shape": "RETRO",
   "plan": "MOON",
   "color": "RED",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +2 [Budget]."
  },
  "R002Y": {
   "shape": "RETRO",
   "plan": "MOON",
   "color": "YELLOW",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +2 [Budget]."
  },
  "R003B": {
   "shape": "SCALLOP",
   "plan": "MOON",
   "color": "BLUE",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 2 Project cards."
  },
  "R003G": {
   "shape": "SCALLOP",
   "plan": "MOON",
   "color": "GREEN",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 2 Project cards."
  },
  "R003R": {
   "shape": "SCALLOP",
   "plan": "MOON",
   "color": "RED",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 2 Project cards."
  },
  "R003Y": {
   "shape": "SCALLOP",
   "plan": "MOON",
   "color": "YELLOW",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, draw 2 Project cards."
  },
  "R004B": {
   "shape": "CONCORDE",
   "plan": "MOON",
   "color": "BLUE",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +4 [Budget]."
  },
  "R004G": {
   "shape": "CONCORDE",
   "plan": "MOON",
   "color": "GREEN",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +4 [Budget]."
  },
  "R004R": {
   "shape": "CONCORDE",
   "plan": "MOON",
   "color": "RED",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +4 [Budget]."
  },
  "R004Y": {
   "shape": "CONCORDE",
   "plan": "MOON",
   "color": "YELLOW",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, +4 [Budget]."
  },
  "R005B": {
   "shape": "STEALTH",
   "plan": "MOON",
   "color": "BLUE",
   "launchCost": 30,
   "fame": 15,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, you may choose up to 2 Project cards from your hand and pay their deployment costs to deploy them to this satellite. The \"On Deploy\" and \"On Launch\" effects of the deployed cards are triggered."
  },
  "R005G": {
   "shape": "STEALTH",
   "plan": "MOON",
   "color": "GREEN",
   "launchCost": 30,
   "fame": 15,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, you may choose up to 2 Project cards from your hand and pay their deployment costs to deploy them to this satellite. The \"On Deploy\" and \"On Launch\" effects of the deployed cards are triggered."
  },
  "R005R": {
   "shape": "STEALTH",
   "plan": "MOON",
   "color": "RED",
   "launchCost": 30,
   "fame": 15,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, you may choose up to 2 Project cards from your hand and pay their deployment costs to deploy them to this satellite. The \"On Deploy\" and \"On Launch\" effects of the deployed cards are triggered."
  },
  "R005Y": {
   "shape": "STEALTH",
   "plan": "MOON",
   "color": "YELLOW",
   "launchCost": 30,
   "fame": 15,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ON_LAUNCH",
   "active": "ANY",
   "text": "When launched, you may choose up to 2 Project cards from your hand and pay their deployment costs to deploy them to this satellite. The \"On Deploy\" and \"On Launch\" effects of the deployed cards are triggered."
  },
  "R006": {
   "shape": "HAMMER",
   "plan": "PHOBOS",
   "color": "BLUE",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Launch cost of all your satellites -3."
  },
  "R007": {
   "shape": "RETRO",
   "plan": "PHOBOS",
   "color": "BLUE",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Deployment cost for all your satellites -1."
  },
  "R008": {
   "shape": "SCALLOP",
   "plan": "PHOBOS",
   "color": "BLUE",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "Link cost of all your satellites -1."
  },
  "R009": {
   "shape": "CONCORDE",
   "plan": "PHOBOS",
   "color": "BLUE",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "ANYTIME",
   "kind": "CONSTANT",
   "active": "ACTIVE_ONLY",
   "text": "The [Maya] you receive when you discard a Project card is +2."
  },
  "R010": {
   "shape": "STEALTH",
   "plan": "PHOBOS",
   "color": "BLUE",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "At the start of the Budget Phase, +2 [Maya]."
  },
  "R011": {
   "shape": "HAMMER",
   "plan": "DEIMOS",
   "color": "GREEN",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "The Proposal Action Selection Bonus becomes \"draw 6 cards and keep 3 in hand\"."
  },
  "R012": {
   "shape": "RETRO",
   "plan": "DEIMOS",
   "color": "GREEN",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "As the Launch Action Selection Bonus, +1 [Budget]."
  },
  "R013": {
   "shape": "SCALLOP",
   "plan": "DEIMOS",
   "color": "GREEN",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "As the Deploy Action Selection Bonus, draw 1 additional Project card."
  },
  "R014": {
   "shape": "CONCORDE",
   "plan": "DEIMOS",
   "color": "GREEN",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "For the Establish Link Action Selection Bonus, the areas you can choose include all adjacent areas, in addition to the areas ahead of and behind the heading."
  },
  "R015": {
   "shape": "STEALTH",
   "plan": "DEIMOS",
   "color": "GREEN",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "The [Maya] gained from the Budget Action Selection Bonus is +3."
  },
  "R016": {
   "shape": "HAMMER",
   "plan": "EUROPA",
   "color": "RED",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "PROPOSAL_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Proposal action yourself."
  },
  "R017": {
   "shape": "RETRO",
   "plan": "EUROPA",
   "color": "RED",
   "launchCost": 25,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "DEPLOYED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Deploy action yourself."
  },
  "R018": {
   "shape": "SCALLOP",
   "plan": "EUROPA",
   "color": "RED",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Establish Link action yourself."
  },
  "R019": {
   "shape": "CONCORDE",
   "plan": "EUROPA",
   "color": "RED",
   "launchCost": 30,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LAUNCHED_OR_TURNED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Launch/Turn action yourself."
  },
  "R020": {
   "shape": "STEALTH",
   "plan": "EUROPA",
   "color": "RED",
   "launchCost": 35,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "BUDGET_PHASE",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "If [CREW], [DEVICE] and [SOFTWARE] are deployed to this satellite, you can gain the Action Selection Bonus by paying 3 [Maya], even if you did not choose the Budget action yourself."
  },
  "R021": {
   "shape": "HAMMER",
   "plan": "GANYMEDE",
   "color": "YELLOW",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [A][C][E][G], link cost -2."
  },
  "R022": {
   "shape": "RETRO",
   "plan": "GANYMEDE",
   "color": "YELLOW",
   "launchCost": 15,
   "fame": 5,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link in region [B][D][F][H], link cost -2."
  },
  "R023": {
   "shape": "SCALLOP",
   "plan": "GANYMEDE",
   "color": "YELLOW",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link on a [Land] area, link cost -3."
  },
  "R024": {
   "shape": "CONCORDE",
   "plan": "GANYMEDE",
   "color": "YELLOW",
   "launchCost": 20,
   "fame": 10,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link on a [Sea] area, link cost -2."
  },
  "R025": {
   "shape": "STEALTH",
   "plan": "GANYMEDE",
   "color": "YELLOW",
   "launchCost": 30,
   "fame": 15,
   "fuelSlots": 2,
   "timing": "LINKED",
   "kind": "ACTIVATED",
   "active": "ACTIVE_ONLY",
   "text": "When this satellite establishes a link, link cost -2."
  }
 },
 "rewards": {
  "T001": {
   "name": "Sea Hegemony",
   "compare": "Number of linked [Sea] areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T002": {
   "name": "Land Hegemony",
   "compare": "Number of linked [Land] areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T003": {
   "name": "Independent Hegemony",
   "compare": "Number of separate groups of linked areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T004": {
   "name": "Chain Hegemony",
   "compare": "Largest number of connected linked areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T005": {
   "name": "Euro-Africa Pacific Hegemony",
   "compare": "Number of links in regions [A][C][E][G]",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T006": {
   "name": "Russia-Asia North-South Hegemony",
   "compare": "Number of links in regions [B][D][F][H]",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T007": {
   "name": "Watchful Tiger Hegemony",
   "compare": "Number of linked areas that you were not the first to link",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T008": {
   "name": "Fortress Hegemony",
   "compare": "Number of linked areas adjacent to a base",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T009": {
   "name": "Regiment Hegemony",
   "compare": "Number of deployed [CREW] Project cards",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T010": {
   "name": "Drive Hegemony",
   "compare": "Number of deployed [DEVICE] Project cards",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T011": {
   "name": "Cyber Hegemony",
   "compare": "Number of deployed [SOFTWARE] Project cards",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T012": {
   "name": "Orbit Hegemony",
   "compare": "Number of satellites with [CREW], [DEVICE] and [SOFTWARE] Project cards all deployed",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T013": {
   "name": "Star Vein Hegemony",
   "compare": "Budget value",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T014": {
   "name": "Maya Hegemony",
   "compare": "Total [Maya] held",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T015": {
   "name": "Flame Hegemony",
   "compare": "Total remaining fuel of your active satellites",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T016": {
   "name": "Fiend Hegemony",
   "compare": "Maximum loaded fuel of a single satellite",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T017": {
   "name": "Plains Hegemony",
   "compare": "Number of linked Standard Land and Forest areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T018": {
   "name": "Evening Calm Hegemony",
   "compare": "Number of linked Standard Sea areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T019": {
   "name": "Foothills Hegemony",
   "compare": "Number of linked [Sea] areas adjacent to a Mountain area",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T020": {
   "name": "Ice Mist Hegemony",
   "compare": "Number of linked [Sea] areas adjacent to a Snowfield area",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T021": {
   "name": "Planting Hegemony",
   "compare": "Number of linked [Sea] areas adjacent to a Forest area",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T022": {
   "name": "Sacred Realm Hegemony",
   "compare": "Largest number of area types within a connected group of linked areas",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T023": {
   "name": "Abyss Hegemony",
   "compare": "Number of linked [Sea] areas adjacent to a Deep Sea area",
   "points": [
    18,
    12,
    6,
    0
   ]
  },
  "T024": {
   "name": "Evergreen Hegemony",
   "compare": "Number of linked [Land] areas adjacent to a Reef area",
   "points": [
    18,
    12,
    6,
    0
   ]
  }
 },
 "missions": {
  "M001": {
   "name": "LOOP",
   "fameEasy": 5,
   "fameFull": 17
  },
  "M002": {
   "name": "BUMP",
   "fameEasy": 5,
   "fameFull": 18
  },
  "M003": {
   "name": "ARCH",
   "fameEasy": 5,
   "fameFull": 18
  },
  "M004": {
   "name": "PILE",
   "fameEasy": 5,
   "fameFull": 18
  },
  "M005": {
   "name": "RECOIL",
   "fameEasy": 8,
   "fameFull": 15
  },
  "M006": {
   "name": "WING",
   "fameEasy": 7,
   "fameFull": 16
  },
  "M007": {
   "name": "FISH",
   "fameEasy": 9,
   "fameFull": 17
  },
  "M008": {
   "name": "EDGE",
   "fameEasy": 9,
   "fameFull": 17
  },
  "M009": {
   "name": "LOCK",
   "fameEasy": 5,
   "fameFull": 24
  },
  "M010": {
   "name": "TURTLE",
   "fameEasy": 5,
   "fameFull": 24
  }
 },
 "actions": {
  "A001": {
   "numeral": "I",
   "name": "Budget",
   "main": "Gain [Maya] equal to your [Budget].\nThis card can be plotted in consecutive rounds.",
   "bonus": "+6 [Maya]."
  },
  "A002": {
   "numeral": "II",
   "name": "Deploy",
   "main": "Choose 1 of your satellites, pay the deployment cost, and deploy a Project card. When deploying to an active satellite, deployment cost +5.",
   "bonus": "Choose one of the following.\ni) Deploy 1 additional card.\nii) Draw 1 Project card."
  },
  "A003": {
   "numeral": "III",
   "name": "Launch / Turn",
   "main": "Choose one of the following.\nLaunch: Choose 1 inactive satellite. Launch it from your launch base in a direction of your choice.\nTurn: Choose 1 active satellite. Turn that satellite's direction.",
   "bonus": "Choose one of the following.\ni) Choose 1 active satellite and turn it.\nii) +1 [Budget]."
  },
  "A004": {
   "numeral": "IV",
   "name": "Establish Link",
   "main": "Choose 1 active satellite. Pay the link cost and establish a link with the tile directly below that satellite.",
   "bonus": "Choose 1 active satellite. Pay the link cost and establish a link with the tile directly below it or an adjacent tile ahead of or behind its heading."
  },
  "A005": {
   "numeral": "V",
   "name": "Proposal",
   "main": "Draw 2 Project cards, keep 1 of them in hand, and discard 1.",
   "bonus": "Instead of the above, draw 5 Project cards, keep 2 of them in hand, and discard 3."
  }
 }
};
