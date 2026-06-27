from app.services.moodle import call


def _append_token(url: str | None, token: str) -> str | None:
    if not url:
        return url
    separator = "&" if "?" in url else "?"
    return f"{url}{separator}token={token}"


def _find_module_in_contents(course_id: int, cmid: int, token: str) -> dict:
    sections = call(
        "core_course_get_contents",
        courseid=course_id,
        token=token,
    )

    if not isinstance(sections, list):
        return {}

    for section in sections:
        for module in section.get("modules", []):
            if module.get("id") == cmid:
                return module

    return {}


def _normalize_contents(contents: list, token: str) -> list:
    normalized = []

    for item in contents or []:
        entry = dict(item)
        if entry.get("fileurl"):
            entry["fileurl"] = _append_token(entry["fileurl"], token)
        normalized.append(entry)

    return normalized


def _find_by_instance(items: list, instance: int) -> dict:
    for item in items or []:
        if item.get("id") == instance or item.get("instance") == instance:
            return item
    return {}


def _find_by_coursemodule(items: list, cmid: int) -> dict:
    for item in items or []:
        if item.get("coursemodule") == cmid:
            return item
    return {}


def _fetch_assign_details(instance: int, course_id: int, cmid: int, token: str) -> dict:
    data = call(
        "mod_assign_get_assignments",
        courseids=[course_id],
        token=token,
    )

    assignment = {}
    for course in data.get("courses", []):
        assignment = _find_by_instance(course.get("assignments", []), instance)
        if assignment:
            break

    status = {}
    if assignment:
        status = call(
            "mod_assign_get_submission_status",
            assignid=assignment.get("id", instance),
            token=token,
        )

    return {
        "assignment": assignment,
        "submission_status": status,
    }


def _fetch_quiz_details(instance: int, course_id: int, cmid: int, token: str) -> dict:
    quizzes = call(
        "mod_quiz_get_quizzes_by_courses",
        courseids=[course_id],
        token=token,
    )

    quiz = _find_by_coursemodule(quizzes.get("quizzes", []), cmid)
    if not quiz:
        quiz = _find_by_instance(quizzes.get("quizzes", []), instance)

    attempts = {}
    if quiz:
        attempts = call(
            "mod_quiz_get_user_attempts",
            quizid=quiz.get("id", instance),
            status="all",
            token=token,
        )

    return {
        "quiz": quiz,
        "attempts": attempts,
    }


def _fetch_forum_details(instance: int, course_id: int, token: str) -> dict:
    forums = call(
        "mod_forum_get_forums_by_courses",
        courseids=[course_id],
        token=token,
    )

    forum = _find_by_instance(forums, instance)

    discussions = {}
    if forum:
        discussions = call(
            "mod_forum_get_forum_discussions",
            forumid=forum.get("id", instance),
            token=token,
        )

    return {
        "forum": forum,
        "discussions": discussions,
    }


def _fetch_book_details(instance: int, course_id: int, token: str) -> dict:
    books = call(
        "mod_book_get_books_by_courses",
        courseids=[course_id],
        token=token,
    )

    book = _find_by_instance(books.get("books", []), instance)

    chapters = []
    for chapter in book.get("chapters", []) if book else []:
        chapter_id = chapter.get("id")
        if not chapter_id:
            chapters.append(chapter)
            continue

        contents = call(
            "mod_book_get_book_contents_by_chapterid",
            chapterid=chapter_id,
            token=token,
        )
        chapters.append(
            {
                **chapter,
                "contents": contents.get("contents", contents),
            }
        )

    return {
        "book": book,
        "chapters": chapters,
    }


def _fetch_mod_by_courses(
    function: str,
    key: str,
    instance: int,
    course_id: int,
    cmid: int,
    token: str,
) -> dict:
    data = call(
        function,
        courseids=[course_id],
        token=token,
    )

    items = data.get(key, data if isinstance(data, list) else [])
    item = _find_by_coursemodule(items, cmid)
    if not item:
        item = _find_by_instance(items, instance)

    return item


def _fetch_type_details(
    modname: str,
    instance: int,
    course_id: int,
    cmid: int,
    token: str,
) -> dict:
    if modname == "assign":
        return _fetch_assign_details(instance, course_id, cmid, token)

    if modname == "quiz":
        return _fetch_quiz_details(instance, course_id, cmid, token)

    if modname == "forum":
        return _fetch_forum_details(instance, course_id, token)

    if modname == "book":
        return _fetch_book_details(instance, course_id, token)

    if modname == "resource":
        return {
            "resource": _fetch_mod_by_courses(
                "mod_resource_get_resources_by_courses",
                "resources",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "url":
        return {
            "url": _fetch_mod_by_courses(
                "mod_url_get_urls_by_courses",
                "urls",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "page":
        page = _fetch_mod_by_courses(
            "mod_page_get_pages_by_courses",
            "pages",
            instance,
            course_id,
            cmid,
            token,
        )
        return {"page": page, "content": page.get("content", "")}

    if modname == "folder":
        return {
            "folder": _fetch_mod_by_courses(
                "mod_folder_get_folders_by_courses",
                "folders",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "label":
        return {
            "label": _fetch_mod_by_courses(
                "mod_label_get_labels_by_courses",
                "labels",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "choice":
        return {
            "choice": _fetch_mod_by_courses(
                "mod_choice_get_choices_by_courses",
                "choices",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "feedback":
        return {
            "feedback": _fetch_mod_by_courses(
                "mod_feedback_get_feedbacks_by_courses",
                "feedbacks",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "glossary":
        return {
            "glossary": _fetch_mod_by_courses(
                "mod_glossary_get_glossaries_by_courses",
                "glossaries",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "lesson":
        return {
            "lesson": _fetch_mod_by_courses(
                "mod_lesson_get_lessons_by_courses",
                "lessons",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "wiki":
        return {
            "wiki": _fetch_mod_by_courses(
                "mod_wiki_get_wikis_by_courses",
                "wikis",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "workshop":
        return {
            "workshop": _fetch_mod_by_courses(
                "mod_workshop_get_workshops_by_courses",
                "workshops",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "scorm":
        return {
            "scorm": _fetch_mod_by_courses(
                "mod_scorm_get_scorms_by_courses",
                "scorms",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "h5pactivity":
        return {
            "h5pactivity": _fetch_mod_by_courses(
                "mod_h5pactivity_get_h5pactivities_by_courses",
                "h5pactivities",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "chat":
        return {
            "chat": _fetch_mod_by_courses(
                "mod_chat_get_chats_by_courses",
                "chats",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "data":
        return {
            "data": _fetch_mod_by_courses(
                "mod_data_get_databases_by_courses",
                "databases",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname in {"lti", "ltiexternaltool"}:
        return {
            "lti": _fetch_mod_by_courses(
                "mod_lti_get_tools_by_courses",
                "ltis",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "bigbluebuttonbn":
        return {
            "bigbluebuttonbn": _fetch_mod_by_courses(
                "mod_bigbluebuttonbn_get_bigbluebuttonbns_by_courses",
                "bigbluebuttonbns",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    if modname == "imscp":
        return {
            "imscp": _fetch_mod_by_courses(
                "mod_imscp_get_imscps_by_courses",
                "imscps",
                instance,
                course_id,
                cmid,
                token,
            )
        }

    return {}


def get_activity(cmid: int, token: str) -> dict:
    cm_data = call(
        "core_course_get_course_module",
        cmid=cmid,
        token=token,
    )

    if isinstance(cm_data, dict) and cm_data.get("exception"):
        return cm_data

    cm = cm_data.get("cm", cm_data)
    modname = cm.get("modname", "")
    instance = cm.get("instance", 0)
    course_id = cm.get("course", 0)

    module = _find_module_in_contents(course_id, cmid, token)
    contents = _normalize_contents(module.get("contents", []), token)

    details = _fetch_type_details(
        modname,
        instance,
        course_id,
        cmid,
        token,
    )

    description = module.get("description", "")
    if not description:
        for key in ("intro", "content"):
            for source in (details.get(modname), details.get("page"), details.get("resource")):
                if isinstance(source, dict) and source.get(key):
                    description = source[key]
                    break
            if description:
                break

    url = module.get("url") or cm.get("url")
    if modname == "url":
        url = details.get("url", {}).get("externalurl") or url

    return {
        "id": cm.get("id", cmid),
        "name": module.get("name") or cm.get("name", ""),
        "module": modname,
        "description": description,
        "url": url,
        "visible": cm.get("visible", module.get("visible", 1)),
        "contents": contents,
        "completion": module.get("completiondata", {}),
        "details": details,
        "course_id": course_id,
        "instance": instance,
        "raw": cm_data,
    }
