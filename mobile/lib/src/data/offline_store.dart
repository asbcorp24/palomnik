import 'dart:convert';

import 'package:path/path.dart' as path;
import 'package:sqflite/sqflite.dart';

class OfflineStore {
  OfflineStore._();

  static final OfflineStore instance = OfflineStore._();
  Database? _database;

  Future<Database> get database async {
    if (_database != null) return _database!;
    final root = await getDatabasesPath();
    _database = await openDatabase(
      path.join(root, 'moscow_pilgrim_offline.db'),
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE cache_entries (
            cache_key TEXT PRIMARY KEY,
            payload TEXT NOT NULL,
            updated_at INTEGER NOT NULL
          )
        ''');
        await db.execute('''
          CREATE TABLE saved_objects (
            object_id INTEGER PRIMARY KEY,
            slug TEXT NOT NULL,
            payload TEXT NOT NULL,
            saved_at INTEGER NOT NULL
          )
        ''');
        await db.execute('''
          CREATE TABLE pending_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action_type TEXT NOT NULL,
            payload TEXT NOT NULL,
            created_at INTEGER NOT NULL
          )
        ''');
      },
    );
    return _database!;
  }

  Future<void> putCache(String key, dynamic payload) async {
    final db = await database;
    await db.insert(
      'cache_entries',
      {
        'cache_key': key,
        'payload': jsonEncode(payload),
        'updated_at': DateTime.now().millisecondsSinceEpoch,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  Future<dynamic> getCache(String key) async {
    final db = await database;
    final rows = await db.query('cache_entries', where: 'cache_key = ?', whereArgs: [key], limit: 1);
    if (rows.isEmpty) return null;
    return jsonDecode(rows.first['payload']! as String);
  }

  Future<void> saveObject(Map<String, dynamic> object) async {
    final db = await database;
    await db.insert(
      'saved_objects',
      {
        'object_id': object['id'],
        'slug': object['slug'],
        'payload': jsonEncode(object),
        'saved_at': DateTime.now().millisecondsSinceEpoch,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  Future<void> removeObject(int objectId) async {
    final db = await database;
    await db.delete('saved_objects', where: 'object_id = ?', whereArgs: [objectId]);
  }

  Future<bool> isObjectSaved(int objectId) async {
    final db = await database;
    final rows = await db.query('saved_objects', columns: ['object_id'], where: 'object_id = ?', whereArgs: [objectId], limit: 1);
    return rows.isNotEmpty;
  }

  Future<List<Map<String, dynamic>>> savedObjects() async {
    final db = await database;
    final rows = await db.query('saved_objects', orderBy: 'saved_at DESC');
    return rows.map((row) => Map<String, dynamic>.from(jsonDecode(row['payload']! as String) as Map)).toList();
  }

  Future<void> enqueue(String actionType, Map<String, dynamic> payload) async {
    final db = await database;
    await db.insert('pending_actions', {
      'action_type': actionType,
      'payload': jsonEncode(payload),
      'created_at': DateTime.now().millisecondsSinceEpoch,
    });
  }

  Future<List<Map<String, dynamic>>> pendingActions() async {
    final db = await database;
    final rows = await db.query('pending_actions', orderBy: 'id');
    return rows.map((row) => {
          'id': row['id'],
          'action_type': row['action_type'],
          'payload': Map<String, dynamic>.from(jsonDecode(row['payload']! as String) as Map),
        }).toList();
  }

  Future<void> removePendingAction(int id) async {
    final db = await database;
    await db.delete('pending_actions', where: 'id = ?', whereArgs: [id]);
  }
}
