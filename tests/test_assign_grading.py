from app.services.assign_grading import grading_status_label


def test_grading_status_needs_grading() -> None:
    label = grading_status_label({"requiregrading": True, "submitted": True}, None)
    assert label == "Needs grading"


def test_grading_status_graded() -> None:
    label = grading_status_label({"requiregrading": False}, {"grade": 85})
    assert label == "Graded"
