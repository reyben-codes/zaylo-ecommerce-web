# Hub-to-hub parcel delivery

The seller selects the origin logistics partner and uses the existing seller pickup or hub handover process. New checkouts retain the recipient's city and barangay names and PSGC codes on the order; subsequent edits to a saved address do not change this snapshot.

For example, a Metro Manila origin hub receives the seller's parcel, dispatches it to an approved Santa Cruz, Laguna hub in the same registered network, and the Santa Cruz hub confirms receipt using the waybill tracking number. The destination hub sorts the parcel into its saved barangay before assigning one of its on-shift local delivery riders. A parcel already in the correct city goes directly to barangay sorting.

The Logistics Center's **Hub transfers** page has dispatch, incoming receipt, and recent route history sections. Both dispatch and receipt require the correct tracking number. Receipt also requires the physical-receipt and destination-verification checklist. Repeated or stale requests cannot duplicate either handover. The incoming sidebar count shows transfers awaiting receipt at that hub.

Transfers are limited to active, approved hubs in the same `network_brand` (or an explicitly shared parent for older unbranded branches). The destination must match the buyer's delivery city. Ambiguous hub choices remain an explicit staff selection; a single matching hub can be planned before pickup. Missing hubs or incomplete hub locations require setup by the administrator. No hubs or addresses are fabricated.

Parcels between hubs cannot be sorted, assigned to a last-mile rider, or advanced through the old local workflow. Operational custody moves only after destination receipt; the origin can still see its route in transfer history. The destination uses the existing sorting, local-rider assignment, attendance, delivery proof and buyer tracking workflows. Transfer and receipt notes are recorded in order history for website and mobile tracking.

The original `logistics_provider_id` remains the contracted provider for existing settlement records. Transfers add no shipping charge and do not rewrite agreed amounts. New rider road-distance quotes use seller-to-origin-hub for pickup and destination-hub-to-buyer for delivery, within the existing funded budget. Completed rider legs retain separate employer/hub attribution. Existing financial quotes remain fixed; hub-to-hub line-haul charges or an automatic revenue split between branches are not added by this workflow.

Older parcels can recover a destination only when a structured saved address still matches their full delivery address exactly. Parcels without an unambiguous location retain their earlier workflow; do not infer their hub destination from free-text fragments. Transfer records remain part of the shipment, so the existing explicit financial/order reset also removes them without affecting hubs, users, or product records.

Run `php artisan migrate` before deploying the updated application. The existing mobile tracking API exposes the new stage and route messages without an APK change. Validate with `php artisan test --filter=HubTransferTest`.
