class UserProfile {
  const UserProfile({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.avatarUrl,
    this.birthDate,
    this.preferences = const {},
    this.isVerifiedOrganizer = false,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? avatarUrl;
  final String? birthDate;
  final Map<String, dynamic> preferences;
  final bool isVerifiedOrganizer;

  factory UserProfile.fromJson(Map<String, dynamic> json) => UserProfile(
        id: _asInt(json['id']),
        name: json['name']?.toString() ?? '',
        email: json['email']?.toString() ?? '',
        phone: _nullableString(json['phone']),
        avatarUrl: _nullableString(json['avatar_url']),
        birthDate: _nullableString(json['birth_date']),
        preferences: _asMap(json['preferences']),
        isVerifiedOrganizer: json['is_verified_organizer'] == true,
      );
}

class PilgrimageObjectModel {
  const PilgrimageObjectModel({
    required this.id,
    required this.slug,
    required this.name,
    this.typeName,
    this.typeSlug,
    this.shortDescription,
    this.description,
    this.history,
    this.address,
    this.latitude,
    this.longitude,
    this.coverUrl,
    this.phone,
    this.email,
    this.website,
    this.schedule,
    this.parking,
    this.accessibility,
    this.sanctities = const [],
    this.media = const [],
  });

  final int id;
  final String slug;
  final String name;
  final String? typeName;
  final String? typeSlug;
  final String? shortDescription;
  final String? description;
  final String? history;
  final String? address;
  final double? latitude;
  final double? longitude;
  final String? coverUrl;
  final String? phone;
  final String? email;
  final String? website;
  final String? schedule;
  final String? parking;
  final String? accessibility;
  final List<String> sanctities;
  final List<MediaModel> media;

  factory PilgrimageObjectModel.fromJson(Map<String, dynamic> json) {
    final location = _asMap(json['location']);
    final contacts = _asMap(json['contacts']);
    final amenities = _asMap(json['amenities']);
    final type = _asMap(json['type']);
    final cover = _asMap(json['cover']);
    final rawSanctities = _asList(json['sanctities']);

    return PilgrimageObjectModel(
      id: _asInt(json['id']),
      slug: json['slug']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      typeName: _nullableString(type['name']),
      typeSlug: _nullableString(type['slug']),
      shortDescription: _nullableString(json['short_description']),
      description: _nullableString(json['description']),
      history: _nullableString(json['history']),
      address: _nullableString(json['address'] ?? location['address']),
      latitude: _asDouble(json['latitude'] ?? location['latitude']),
      longitude: _asDouble(json['longitude'] ?? location['longitude']),
      coverUrl: _nullableString(json['cover_url'] ?? cover['url']),
      phone: _nullableString(json['phone'] ?? contacts['phone']),
      email: _nullableString(json['email'] ?? contacts['email']),
      website: _nullableString(json['website'] ?? contacts['website']),
      schedule: _nullableString(json['schedule']),
      parking: _nullableString(json['parking'] ?? amenities['parking']),
      accessibility: _nullableString(json['accessibility'] ?? amenities['accessibility']),
      sanctities: rawSanctities
          .map((item) => item is Map ? item['name']?.toString() : item?.toString())
          .whereType<String>()
          .where((value) => value.isNotEmpty)
          .toList(),
      media: _asList(json['media'])
          .whereType<Map>()
          .map((item) => MediaModel.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class MediaModel {
  const MediaModel({required this.type, this.url, this.title});

  final String type;
  final String? url;
  final String? title;

  factory MediaModel.fromJson(Map<String, dynamic> json) => MediaModel(
        type: json['type']?.toString() ?? 'image',
        url: _nullableString(json['url']),
        title: _nullableString(json['title']),
      );
}

class TripModel {
  const TripModel({
    required this.id,
    this.title,
    this.startsAt,
    this.endsAt,
    this.meetingPoint,
    this.capacity,
    this.bookedCount = 0,
    this.price,
    this.status,
  });

  final int id;
  final String? title;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final String? meetingPoint;
  final int? capacity;
  final int bookedCount;
  final double? price;
  final String? status;

  factory TripModel.fromJson(Map<String, dynamic> json) => TripModel(
        id: _asInt(json['id']),
        title: _nullableString(json['title']),
        startsAt: _asDate(json['starts_at']),
        endsAt: _asDate(json['ends_at']),
        meetingPoint: _nullableString(json['meeting_point']),
        capacity: _asNullableInt(json['capacity']),
        bookedCount: _asInt(json['booked_count']),
        price: _asDouble(json['price']),
        status: _nullableString(json['status']),
      );
}

class RouteModel {
  const RouteModel({
    required this.id,
    required this.slug,
    required this.title,
    this.category,
    this.difficulty,
    this.durationDays,
    this.durationMinutes,
    this.shortDescription,
    this.description,
    this.program,
    this.basePrice,
    this.coverUrl,
    this.objectsCount,
    this.objects = const [],
    this.trips = const [],
  });

  final int id;
  final String slug;
  final String title;
  final String? category;
  final String? difficulty;
  final int? durationDays;
  final int? durationMinutes;
  final String? shortDescription;
  final String? description;
  final String? program;
  final double? basePrice;
  final String? coverUrl;
  final int? objectsCount;
  final List<PilgrimageObjectModel> objects;
  final List<TripModel> trips;

  factory RouteModel.fromJson(Map<String, dynamic> json) => RouteModel(
        id: _asInt(json['id']),
        slug: json['slug']?.toString() ?? '',
        title: json['title']?.toString() ?? '',
        category: _nullableString(json['category']),
        difficulty: _nullableString(json['difficulty']),
        durationDays: _asNullableInt(json['duration_days']),
        durationMinutes: _asNullableInt(json['duration_minutes']),
        shortDescription: _nullableString(json['short_description']),
        description: _nullableString(json['description']),
        program: _nullableString(json['program']),
        basePrice: _asDouble(json['base_price']),
        coverUrl: _nullableString(json['cover_url']),
        objectsCount: _asNullableInt(json['objects_count']),
        objects: _asList(json['objects'])
            .whereType<Map>()
            .map((item) => PilgrimageObjectModel.fromJson(Map<String, dynamic>.from(item)))
            .toList(),
        trips: _asList(json['trips'])
            .whereType<Map>()
            .map((item) => TripModel.fromJson(Map<String, dynamic>.from(item)))
            .toList(),
      );
}

class EventModel {
  const EventModel({
    required this.id,
    required this.slug,
    required this.title,
    this.type,
    this.typeLabel,
    this.shortDescription,
    this.description,
    this.startsAt,
    this.endsAt,
    this.allDay = false,
    this.location,
    this.address,
    this.latitude,
    this.longitude,
    this.registrationUrl,
    this.contactPhone,
    this.contactEmail,
    this.icsUrl,
    this.object,
  });

  final int id;
  final String slug;
  final String title;
  final String? type;
  final String? typeLabel;
  final String? shortDescription;
  final String? description;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final bool allDay;
  final String? location;
  final String? address;
  final double? latitude;
  final double? longitude;
  final String? registrationUrl;
  final String? contactPhone;
  final String? contactEmail;
  final String? icsUrl;
  final PilgrimageObjectModel? object;

  factory EventModel.fromJson(Map<String, dynamic> json) => EventModel(
        id: _asInt(json['id']),
        slug: json['slug']?.toString() ?? '',
        title: json['title']?.toString() ?? '',
        type: _nullableString(json['type']),
        typeLabel: _nullableString(json['type_label']),
        shortDescription: _nullableString(json['short_description']),
        description: _nullableString(json['description']),
        startsAt: _asDate(json['starts_at']),
        endsAt: _asDate(json['ends_at']),
        allDay: json['all_day'] == true,
        location: _nullableString(json['location']),
        address: _nullableString(json['address']),
        latitude: _asDouble(json['latitude']),
        longitude: _asDouble(json['longitude']),
        registrationUrl: _nullableString(json['registration_url']),
        contactPhone: _nullableString(json['contact_phone']),
        contactEmail: _nullableString(json['contact_email']),
        icsUrl: _nullableString(json['ics_url']),
        object: json['object'] is Map
            ? PilgrimageObjectModel.fromJson(Map<String, dynamic>.from(json['object'] as Map))
            : null,
      );
}

class BlogPostModel {
  const BlogPostModel({
    required this.id,
    required this.slug,
    required this.title,
    this.excerpt,
    this.body,
    this.publishedAt,
    this.authorName,
    this.authorAvatarUrl,
    this.media = const [],
  });

  final int id;
  final String slug;
  final String title;
  final String? excerpt;
  final String? body;
  final DateTime? publishedAt;
  final String? authorName;
  final String? authorAvatarUrl;
  final List<MediaModel> media;

  factory BlogPostModel.fromJson(Map<String, dynamic> json) {
    final author = _asMap(json['author']);
    return BlogPostModel(
      id: _asInt(json['id']),
      slug: json['slug']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      excerpt: _nullableString(json['excerpt']),
      body: _nullableString(json['body']),
      publishedAt: _asDate(json['published_at']),
      authorName: _nullableString(author['name']),
      authorAvatarUrl: _nullableString(author['avatar_url']),
      media: _asList(json['media'])
          .whereType<Map>()
          .map((item) => MediaModel.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class JointPilgrimageModel {
  const JointPilgrimageModel({
    required this.id,
    required this.slug,
    required this.title,
    required this.description,
    this.startsAt,
    this.endsAt,
    this.meetingPlace,
    this.maxParticipants,
    this.participantsCount = 1,
    this.availablePlaces,
    this.transportMode,
    this.joinMode,
    this.status,
    this.organizerName,
    this.organizerVerified = false,
    this.membershipStatus,
    this.canManage = false,
    this.canDiscuss = false,
    this.messages = const [],
  });

  final int id;
  final String slug;
  final String title;
  final String description;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final String? meetingPlace;
  final int? maxParticipants;
  final int participantsCount;
  final int? availablePlaces;
  final String? transportMode;
  final String? joinMode;
  final String? status;
  final String? organizerName;
  final bool organizerVerified;
  final String? membershipStatus;
  final bool canManage;
  final bool canDiscuss;
  final List<ChatMessageModel> messages;

  factory JointPilgrimageModel.fromJson(Map<String, dynamic> json) {
    final organizer = _asMap(json['organizer']);
    return JointPilgrimageModel(
      id: _asInt(json['id']),
      slug: json['slug']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      startsAt: _asDate(json['starts_at']),
      endsAt: _asDate(json['ends_at']),
      meetingPlace: _nullableString(json['meeting_place']),
      maxParticipants: _asNullableInt(json['max_participants']),
      participantsCount: _asInt(json['participants_count']),
      availablePlaces: _asNullableInt(json['available_places']),
      transportMode: _nullableString(json['transport_mode']),
      joinMode: _nullableString(json['join_mode']),
      status: _nullableString(json['status']),
      organizerName: _nullableString(organizer['name']),
      organizerVerified: organizer['is_verified_organizer'] == true,
      membershipStatus: _nullableString(json['membership_status']),
      canManage: json['can_manage'] == true,
      canDiscuss: json['can_discuss'] == true,
      messages: _asList(json['messages'])
          .whereType<Map>()
          .map((item) => ChatMessageModel.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class ChatMessageModel {
  const ChatMessageModel({
    required this.id,
    required this.body,
    this.isSystem = false,
    this.createdAt,
    this.userName,
  });

  final int id;
  final String body;
  final bool isSystem;
  final DateTime? createdAt;
  final String? userName;

  factory ChatMessageModel.fromJson(Map<String, dynamic> json) {
    final user = _asMap(json['user']);
    return ChatMessageModel(
      id: _asInt(json['id']),
      body: json['body']?.toString() ?? '',
      isSystem: json['is_system'] == true,
      createdAt: _asDate(json['created_at']),
      userName: _nullableString(user['name']),
    );
  }
}

class BookingModel {
  const BookingModel({
    required this.id,
    this.routeTitle,
    this.startsAt,
    this.meetingPoint,
    this.participantsCount = 1,
    this.totalAmount = 0,
    this.status,
    this.paymentStatus,
    this.ticketCode,
    this.qrPayload,
    this.checkedInAt,
    this.checkedInParticipants = 0,
    this.calendarUrl,
  });

  final int id;
  final String? routeTitle;
  final DateTime? startsAt;
  final String? meetingPoint;
  final int participantsCount;
  final double totalAmount;
  final String? status;
  final String? paymentStatus;
  final String? ticketCode;
  final String? qrPayload;
  final DateTime? checkedInAt;
  final int checkedInParticipants;
  final String? calendarUrl;

  factory BookingModel.fromJson(Map<String, dynamic> json) {
    final trip = _asMap(json['trip']);
    final route = _asMap(trip['route']);
    return BookingModel(
      id: _asInt(json['id']),
      routeTitle: _nullableString(route['title'] ?? trip['title']),
      startsAt: _asDate(trip['starts_at']),
      meetingPoint: _nullableString(trip['meeting_point']),
      participantsCount: _asInt(json['participants_count']),
      totalAmount: _asDouble(json['total_amount']) ?? 0,
      status: _nullableString(json['status']),
      paymentStatus: _nullableString(json['payment_status']),
      ticketCode: _nullableString(json['ticket_code']),
      qrPayload: _nullableString(json['qr_payload']),
      checkedInAt: _asDate(json['checked_in_at']),
      checkedInParticipants: _asInt(json['checked_in_participants']),
      calendarUrl: _nullableString(json['calendar_url']),
    );
  }
}

class NotificationModel {
  const NotificationModel({
    required this.id,
    required this.type,
    required this.data,
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String type;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isRead => readAt != null;
  String get title => data['title']?.toString() ?? 'Уведомление';
  String get message => data['message']?.toString() ?? data['body']?.toString() ?? '';

  factory NotificationModel.fromJson(Map<String, dynamic> json) => NotificationModel(
        id: json['id']?.toString() ?? '',
        type: json['type']?.toString() ?? '',
        data: _asMap(json['data']),
        readAt: _asDate(json['read_at']),
        createdAt: _asDate(json['created_at']),
      );
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return <String, dynamic>{};
}

List<dynamic> _asList(dynamic value) => value is List ? value : const [];

int _asInt(dynamic value) {
  if (value is int) return value;
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

int? _asNullableInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  return int.tryParse(value.toString());
}

double? _asDouble(dynamic value) {
  if (value == null) return null;
  if (value is num) return value.toDouble();
  return double.tryParse(value.toString());
}

DateTime? _asDate(dynamic value) {
  if (value == null) return null;
  return DateTime.tryParse(value.toString())?.toLocal();
}

String? _nullableString(dynamic value) {
  final text = value?.toString().trim();
  return text == null || text.isEmpty ? null : text;
}
