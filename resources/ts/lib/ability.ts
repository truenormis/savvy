import { AbilityBuilder, createMongoAbility, MongoAbility } from '@casl/ability'
import { UserRole } from '@/types'

type Actions = 'manage' | 'create' | 'read' | 'update' | 'delete'
type Subjects = 'all' | 'User'

export type AppAbility = MongoAbility<[Actions, Subjects]>

export function defineAbilityFor(role: UserRole | null): AppAbility {
    const { can, cannot, build } = new AbilityBuilder<AppAbility>(createMongoAbility)

    switch (role) {
        case 'admin':
            can('manage', 'all')
            break
        case 'read-write':
            can('manage', 'all')
            cannot('manage', 'User')
            can('read', 'User')
            break
        case 'read-only':
            can('read', 'all')
            break
        default:
            break
    }

    return build()
}
