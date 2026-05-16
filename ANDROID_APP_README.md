# BPOS Android - Offline-First Barbershop POS

Production-ready Android application for barbershop point-of-sale with complete offline-first architecture, conflict resolution, and background sync.

## Architecture Overview

### Core Components

**1. Local Database (Room)**
- SQLite-based persistent storage
- Tables: `transactions`, `expenses`, `users`, `branches`, `services`
- Automatic indexing on frequently queried columns
- Supports offline operation without network

**2. Network Layer (Retrofit + OkHttp)**
- Typed HTTP client with automatic retry
- Auth interceptor for token management
- Logging interceptor for debug builds
- Base URL configurable via BuildConfig

**3. Sync Engine (WorkManager)**
- Background sync every 15 minutes (configurable)
- Automatic retry with exponential backoff (max 3 attempts)
- Immediate sync trigger on network connection
- WorkManager with Hilt integration

**4. Conflict Resolution**
- Last-Write-Wins strategy based on `updated_at` timestamp
- Admin always wins (server-side logic)
- Conflict detection during push sync
- Manual review capability for edge cases

**5. Dependency Injection (Hilt)**
- Singleton scoped database, API client, repositories
- Field injection in Activities/Fragments
- Custom worker factory for WorkManager

## Features

### User Authentication
- Offline login support (cached credentials)
- Session management via DataStore
- Auto-generated device ID for sync tracking
- Role-based access (admin/barber)

### Transaction Management (Transaksi)
- Quick transaction entry via BottomSheet
- Service dropdown from local master data
- Amount and notes fields
- Soft delete support (local and sync)
- Sync status badge (Pending/Synced/Failed/Conflict)

### Expense Tracking (Pengeluaran)
- Category filtering (Operasional, Makan, Semua)
- Validation: meal allowance verification
- Prevent duplicate meal claims per day
- Admin-only editing

### Dashboard
- Today's revenue KPI card
- Customer count today
- Barber commission calculation (50% of revenue minus meals)
- Pending sync count display
- Manual sync button with loading state

### Master Data
- Branches, Services, Users cached locally
- Auto-sync on login via MasterDataSyncWorker
- Daily refresh via background worker
- Barber filtering by branch

## Project Structure

```
app/src/main/
├── java/com/barbershop/pos/
│   ├── BarbershopApp.kt                    # Hilt application class
│   ├── data/
│   │   ├── local/
│   │   │   ├── AppDatabase.kt             # Room database
│   │   │   ├── dao/                        # Data access objects
│   │   │   └── entity/                     # Database entities
│   │   ├── remote/
│   │   │   ├── ApiService.kt              # Retrofit interface
│   │   │   ├── AuthInterceptor.kt         # OkHttp interceptor
│   │   │   ├── ConflictResolver.kt        # Conflict resolution logic
│   │   │   └── dto/                        # API data transfer objects
│   │   ├── sync/
│   │   │   ├── SyncWorker.kt              # Transaction/expense sync
│   │   │   ├── MasterDataSyncWorker.kt    # Master data sync
│   │   │   └── SyncScheduler.kt           # WorkManager scheduling
│   │   ├── AuthPreferences.kt             # DataStore keys
│   │   └── AuthRepository.kt              # Auth state management
│   ├── di/                                 # Hilt modules
│   ├── domain/repository/                  # Business logic repositories
│   └── presentation/
│       ├── login/                          # Login screen
│       ├── main/                           # Dashboard & MainActivity
│       ├── transaction/                    # Transaction UI
│       └── expense/                        # Expense UI
├── res/
│   ├── layout/                            # Activity & fragment layouts
│   ├── menu/                              # ActionBar menus
│   ├── drawable/                          # Drawables & shapes
│   ├── values/                            # Strings, colors, themes
│   └── navigation/                        # Navigation graph
└── AndroidManifest.xml
```

## Data Models

### TransactionEntity
```kotlin
data class TransactionEntity(
    val localId: String,           // UUID for offline tracking
    val remoteId: Int?,            // Server ID when synced
    val userId: Int,               // Barber ID
    val serviceName: String,
    val amount: Int,
    val notes: String = "",
    val createdAt: Long,
    val branchId: Int,
    val syncStatus: SyncStatus,    // PENDING/SYNCED/FAILED/CONFLICT
    val lastUpdated: Long,         // For conflict resolution
    val deviceId: String,
    val conflictResolved: Boolean
)
```

### ExpenseEntity
Same structure as Transaction, with `category` field instead of `serviceName`.

### UserEntity
```kotlin
data class UserEntity(
    val id: Int,
    val username: String,
    val name: String,
    val role: String,             // "admin" or "barber"
    val branchId: Int,
    val mealAllowance: Int
)
```

## Sync Flow

### Push Sync (PENDING → SYNCED/FAILED/CONFLICT)
1. WorkManager triggers SyncWorker every 15 min
2. Query all PENDING transactions + expenses
3. Build request with localId, data, device_id, updated_at
4. POST to `/api/sync.php?type=push`
5. Parse response:
   - Success: Update to SYNCED, store remoteId
   - Conflict: Mark CONFLICT, run ConflictResolver
   - Network error: Retry (exponential backoff, max 3x)

### Pull Sync (Master Data)
1. On login: Trigger MasterDataSyncWorker
2. GET `/api/sync.php?type=pull`
3. Parse branches, services, users
4. Upsert to local database (replace all)
5. Subsequent transactions pull within pull request

### Conflict Resolution
When admin edits a transaction that device also modified offline:
- Compare `lastUpdated` timestamps
- If device > server: Re-mark PENDING, will retry push
- If server > device: Update local to server version, mark SYNCED

## API Endpoints

### POST /api/login.php
```json
Request: { "username": "...", "password": "..." }
Response: { "success": true, "data": { "id": 1, "name": "...", "role": "barber", ... } }
```

### GET /api/sync.php?type=pull
Returns master data + last 30 days of transactions/expenses.

### POST /api/sync.php?type=push
```json
Request: {
  "transactions": [ { "localId", "remoteId", "user_id", "service_name", ... } ],
  "expenses": [ { "localId", "remoteId", "user_id", "category", ... } ]
}
Response: {
  "success": true,
  "results": { "transactions": [ { "localId", "remoteId" } ], ... },
  "conflicts": [ { "localId", "remoteId", "type", "reason" } ]
}
```

## Configuration

### API Base URL
Set in `gradle.properties`:
```
API_BASE_URL=https://your-vps-url.com
```

Or override at runtime (not recommended for production):
```kotlin
-DAPI_BASE_URL=http://192.168.1.100 ./gradlew build
```

### Sync Interval
Edit `SyncScheduler.schedulePeriodic()`:
```kotlin
PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES)  // Change 15 to desired minutes
```

### Retry Policy
In `SyncWorker.doWork()`:
```kotlin
if (runAttemptCount < 3) {  // Change 3 to max retries
```

## Build & Deployment

### Debug Build
```bash
./gradlew assembleDebug
# APK: app/build/outputs/apk/debug/app-debug.apk
```

### Release Build
```bash
./gradlew assembleRelease
# APK: app/build/outputs/apk/release/app-release.apk
```

### ProGuard/R8 Minification
Enabled by default for release builds. Rules in `proguard-rules.pro`.

## Testing

### Unit Tests (Local)
```bash
./gradlew test
```

### Instrumented Tests (Device/Emulator)
```bash
./gradlew connectedAndroidTest
```

## Dependencies (Core)

| Library | Version | Purpose |
|---------|---------|---------|
| Room | 2.6.1 | Local database |
| Retrofit | 2.11.0 | HTTP client |
| OkHttp | 4.12.0 | HTTP layer |
| Hilt | 2.51.1 | Dependency injection |
| WorkManager | 2.9.0 | Background sync |
| Coroutines | 1.8.0 | Async programming |
| DataStore | 1.1.1 | Preferences |
| Navigation | 2.7.7 | Fragment navigation |
| Material | 1.12.0 | UI components |

## Security Considerations

1. **Authentication**: Session stored in encrypted SharedPreferences/DataStore
2. **Network**: OkHttp certificate pinning ready (optional implementation)
3. **Local Storage**: Room database can be encrypted with SQLCipher (optional)
4. **ProGuard**: Enabled for release builds to obfuscate code
5. **Permissions**: Only INTERNET, ACCESS_NETWORK_STATE, POST_NOTIFICATIONS required
6. **cleartext Traffic**: Allowed only for debug builds (remove `usesCleartextTraffic` for prod HTTPS)

## Offline Behavior

- ✅ User can login with cached credentials
- ✅ All data available from local SQLite
- ✅ Add transactions/expenses immediately (stored locally)
- ✅ Sync when network returns (automatic)
- ✅ Sync status shown in UI
- ✅ Pending count displayed
- ✅ Manual sync button always available

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Network offline | Store locally, queue for sync, show badge |
| Login failed | Display error toast, prevent app entry |
| Sync conflict | Mark CONFLICT, apply resolver logic |
| Sync retry exceeded | Mark FAILED, show in UI for manual review |
| Database corruption | App will crash (integrity check in Room) |

## Known Limitations

1. **No offline conflict detection**: Conflicts only resolved during push sync
2. **Single device assumption**: Multi-device sync not fully tested
3. **No end-to-end encryption**: Data encrypted only in transit (HTTPS)
4. **No audit trail**: Soft deletes don't track who/when
5. **No transaction rollback**: All syncs are append-only

## Future Enhancements

- [ ] Selective master data sync (by branch)
- [ ] Batch import/export (CSV)
- [ ] Photo capture for expenses
- [ ] Digital receipts
- [ ] Barcode scanning for services
- [ ] Multi-language support
- [ ] Dark mode
- [ ] Fingerprint auth
- [ ] Push notifications for admin actions
- [ ] Real-time sync via WebSocket

## Troubleshooting

**APK won't install**
- Ensure API level 26+
- Check certificate compatibility

**Sync stuck at PENDING**
- Check `API_BASE_URL` in BuildConfig
- Verify network connectivity
- Check server logs at `/api/sync.php`

**Meal allowance validation failing**
- Verify user mealAllowance cached correctly
- Check ExpenseDao.getUserMealExpenseToday() date format

**Database bloats after syncs**
- Implement periodic cleanup of SYNCED records older than 90 days
- Consider pagination for historical data

## License

Proprietary - Barbershop Management System 2026
