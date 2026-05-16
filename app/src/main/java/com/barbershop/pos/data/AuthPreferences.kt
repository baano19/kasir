package com.barbershop.pos.data

import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey

object AuthPreferences {
    val TOKEN_KEY = stringPreferencesKey("token")
    val USER_ID_KEY = intPreferencesKey("user_id")
    val USERNAME_KEY = stringPreferencesKey("username")
    val NAME_KEY = stringPreferencesKey("name")
    val ROLE_KEY = stringPreferencesKey("role")
    val BRANCH_ID_KEY = intPreferencesKey("branch_id")
    val DEVICE_ID_KEY = stringPreferencesKey("device_id")
    val MEAL_ALLOWANCE_KEY = intPreferencesKey("meal_allowance")
}
